<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourPlanResource;
use App\Http\Requests\StoreTourPlanRequest;
use App\Http\Requests\UpdateTourPlanRequest;
use App\Models\TourPlan;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TourPlanController extends Controller
{
    #[OA\Get(path: '/api/v1/tour-plans', summary: 'Lister les plans de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        responses: [new OA\Response(response: 200, description: 'Liste des plans de tournée')]
    )]
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = TourPlan::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['delegue', 'validePar', 'activities.contact', 'activities.account'])
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->date_debut, fn($q, $v) => $q->where('date_debut', '>=', $v))
            ->when($request->date_fin, fn($q, $v) => $q->where('date_fin', '<=', $v))
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->withCount('activities')
            ->orderByDesc('created_at');

        // Délégué : ses plannings ; hiérarchie : les siens + ceux de son équipe ; admin : tout
        if (!($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']))) {
            $query->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds()));
        }

        return TourPlanResource::collection($query->paginate(30));
    }

    #[OA\Post(path: '/api/v1/tour-plans', summary: 'Créer un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        responses: [new OA\Response(response: 201, description: 'Plan créé avec succès')]
    )]
    public function store(StoreTourPlanRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();
        unset($validated['activity_ids']);

        $plan = TourPlan::create(array_merge($validated, [
            'user_id'       => $user->id,
            'statut'        => $request->input('statut', 'soumis'),
        ]));

        // Visites soumises : celles choisies par le délégué (ou, sans sélection, toutes celles de la période),
        // uniquement les siennes et encore à soumettre (brouillon, ou en attente sans planning)
        $activityIds = $request->input('activity_ids', []);
        $toSubmit = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('statut', ['brouillon', 'planifiee'])
                  ->orWhere(fn ($q2) => $q2->where('statut', 'en_attente')->whereNull('tour_plan_id'));
            })
            ->when(
                !empty($activityIds) && is_array($activityIds),
                fn ($q) => $q->whereIn('id', $activityIds),
                fn ($q) => $q->whereBetween('date_activite', [$plan->date_debut, $plan->date_fin])
            );

        $linkedCount = $toSubmit->update(['tour_plan_id' => $plan->id, 'statut' => 'en_attente']);
        if ($linkedCount === 0) {
            $plan->delete();
            return response()->json(['message' => 'Aucune visite à soumettre : les visites choisies sont déjà soumises ou traitées.'], 422);
        }
        $plan->update(['nb_activites_planifiees' => $linkedCount]);

        // Notification in-app et email aux managers
        $managers = collect();
        if ($user?->manager) {
            $managers->push($user->manager);
        }
        // Seul le supérieur direct du délégué est notifié

        NotificationController::sendNotification(
            $managers,
            'Planning de tournée soumis',
            "Le délégué {$user->name} a soumis le plan de tournée '{$plan->nom}' pour validation.",
            'planning',
            'manager',
            ['tour_plan_id' => (string) $plan->id]
        );

        return new TourPlanResource($plan->load(['delegue', 'activities.contact', 'activities.account']));
    }

    #[OA\Get(path: '/api/v1/tour-plans/{id}', summary: 'Détails d\'un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        responses: [new OA\Response(response: 200, description: 'Détails du plan')]
    )]
    public function show(string $id)
    {
        return new TourPlanResource(TourPlan::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['delegue', 'validePar', 'activities.contact', 'activities.account'])->findOrFail($id));
    }

    #[OA\Put(path: '/api/v1/tour-plans/{id}', summary: 'Mettre à jour un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        responses: [new OA\Response(response: 200, description: 'Plan mis à jour')]
    )]
    public function update(UpdateTourPlanRequest $request, string $id)
    {
        $plan = TourPlan::findOrFail($id);
        $data = $request->validated();
        if (empty($data) && $request->has('statut')) {
            $data['statut'] = $request->input('statut');
        }

        if (isset($data['statut']) && in_array($data['statut'], ['valide', 'rejete'])) {
            $user = auth()->user();
            abort_unless(($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) || $user->manages($plan->user_id), 403, "Ce planning n'appartient pas à un délégué de votre équipe.");
            $data['valide_par'] = auth()->id();
            $data['valide_le'] = now();

            if ($plan->delegue) {
                $statusLabel = $data['statut'] === 'valide' ? 'approuvé' : 'rejeté / à réviser';
                app(\App\Services\NotificationService::class)->send(
                    $plan->delegue,
                    'Planning de tournée ' . ucfirst($statusLabel),
                    "Votre plan de tournée '{$plan->nom}' a été {$statusLabel} par votre manager.",
                    'planning',
                    ['tour_plan_id' => $plan->id]
                );
            }
        }

        $plan->update($data);

        // Si validé, passer les activités rattachées en statut 'validee'
        if (isset($data['statut']) && $data['statut'] === 'valide') {
            Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                ->where(function ($q) use ($plan) {
                    $q->where('tour_plan_id', $plan->id)
                      ->orWhere(function ($q2) use ($plan) {
                          $q2->where('user_id', $plan->user_id)
                             ->whereBetween('date_activite', [$plan->date_debut, $plan->date_fin]);
                      });
                })
                ->where('statut', '!=', 'realisee')
                ->update([
                    'statut' => 'validee',
                    'tour_plan_id' => $plan->id,
                ]);
        }

        // Notification au délégué
        if ($plan->user_id && isset($data['statut'])) {
            $delegate = User::find($plan->user_id);
            if ($delegate) {
                $statusLabel = $data['statut'] === 'valide' ? 'validé' : 'rejeté';
                NotificationController::sendNotification(
                    $delegate,
                    "Planning {$statusLabel}",
                    "Votre plan de tournée '{$plan->nom}' a été {$statusLabel} par votre superviseur.",
                    'planning',
                    'delegate',
                    ['tour_plan_id' => (string) $plan->id]
                );
            }
        }

        return new TourPlanResource($plan->load(['delegue', 'validePar', 'activities.contact', 'activities.account']));
    }

    #[OA\Delete(path: '/api/v1/tour-plans/{id}', summary: 'Supprimer un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    /**
     * Retrait d'un planning : le délégué retire le sien tant qu'il n'est pas validé
     * (brouillon, soumis, refusé) ; la hiérarchie peut retirer ceux de son équipe.
     * Les visites rattachées sont conservées et détachées du planning.
     */
    public function destroy(string $id)
    {
        $tourPlan = TourPlan::findOrFail($id);
        $user = auth()->user();
        $isGlobal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        $isOwner = $tourPlan->user_id === $user->id;

        if (!$isGlobal && !$user->manages($tourPlan->user_id)) {
            abort_unless($isOwner, 403, "Ce planning ne vous appartient pas.");
            abort_unless(in_array($tourPlan->statut, ['brouillon', 'soumis', 'rejete', 'refuse'], true), 422,
                'Un planning validé ne peut plus être retiré.');
        }

        // Les visites encore en attente redeviennent des brouillons, à resoumettre au besoin
        Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->where('tour_plan_id', $tourPlan->id)
            ->where('statut', 'en_attente')
            ->update(['statut' => 'brouillon']);
        Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->where('tour_plan_id', $tourPlan->id)
            ->update(['tour_plan_id' => null]);
        $tourPlan->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/tour-plans/optimize',
        summary: 'Optimiser l\'ordre d\'une tournée par IA/GPS (Mock)',
        security: [['bearerAuth' => []]],
        tags: ['Tour Plans'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tour_plan_id', 'current_lat', 'current_lng'],
                properties: [
                    new OA\Property(property: 'tour_plan_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'current_lat', type: 'number', format: 'float', example: 5.34),
                    new OA\Property(property: 'current_lng', type: 'number', format: 'float', example: -4.01),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tournée optimisée renvoyée avec la durée estimée et les km.'),
        ]
    )]
    public function optimize(Request $request)
    {
        $validated = $request->validate([
            'tour_plan_id' => 'required|exists:tour_plans,id',
            'current_lat'  => 'required|numeric',
            'current_lng'  => 'required|numeric',
        ]);

        $tourPlan = TourPlan::with(['activities.contact', 'activities.account'])->findOrFail($validated['tour_plan_id']);
        $activities = $tourPlan->activities;

        $currentLat = (float) $validated['current_lat'];
        $currentLng = (float) $validated['current_lng'];

        // Algorithme du plus proche voisin (Nearest Neighbor)
        $unvisited = collect($activities->all());
        $ordered = collect();
        $totalDistanceKm = 0.0;

        while ($unvisited->isNotEmpty()) {
            $closestIndex = null;
            $minDistance = INF;

            foreach ($unvisited as $index => $act) {
                $destLat = (float) ($act->account->gps_lat ?? $act->contact->gps_lat ?? $currentLat);
                $destLng = (float) ($act->account->gps_lng ?? $act->contact->gps_lng ?? $currentLng);

                $dist = $this->haversineGreatCircleDistance($currentLat, $currentLng, $destLat, $destLng);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                    $closestIndex = $index;
                }
            }

            if ($closestIndex !== null) {
                $selected = $unvisited->pull($closestIndex);
                $ordered->push($selected);

                $totalDistanceKm += ($minDistance === INF ? 0 : $minDistance);
                $currentLat = (float) ($selected->account->gps_lat ?? $currentLat);
                $currentLng = (float) ($selected->account->gps_lng ?? $currentLng);
            } else {
                break;
            }
        }

        $estimatedDurationMinutes = round(($totalDistanceKm * 3) + ($ordered->count() * 30));

        return response()->json([
            'tour_plan_id'               => $tourPlan->id,
            'total_distance_km'          => round($totalDistanceKm, 2),
            'estimated_duration_minutes' => (int) $estimatedDurationMinutes,
            'ordered_activities'         => $ordered->values(),
        ]);
    }

    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}
