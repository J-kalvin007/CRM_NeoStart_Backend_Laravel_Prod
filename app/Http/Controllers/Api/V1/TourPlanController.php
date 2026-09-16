<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourPlanResource;
use App\Http\Requests\StoreTourPlanRequest;
use App\Http\Requests\UpdateTourPlanRequest;
use App\Models\TourPlan;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TourPlanController extends Controller
{
    #[OA\Get(path: '/api/v1/tour-plans', summary: 'Lister les plans de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        parameters: [
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['brouillon','soumis','valide','en_cours','termine','rejete'])),
            new OA\Parameter(name: 'date_debut', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_fin', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des plans de tournée')]
    )]
    public function index(Request $request)
    {
        $query = TourPlan::with(['delegue', 'validePar'])
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->date_debut, fn($q, $v) => $q->where('date_debut', '>=', $v))
            ->when($request->date_fin, fn($q, $v) => $q->where('date_fin', '<=', $v))
            ->withCount('activities')
            ->orderByDesc('date_debut');
        return TourPlanResource::collection($query->paginate(20));
    }

    #[OA\Post(path: '/api/v1/tour-plans', summary: 'Créer un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['nom', 'date_debut', 'date_fin'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Semaine 42 - Secteur Nord'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Plan créé avec succès')]
    )]
    public function store(StoreTourPlanRequest $request)
    {
        $plan = TourPlan::create(array_merge($request->validated(), ['user_id' => auth()->id()]));
        return new TourPlanResource($plan->load('delegue'));
    }

    #[OA\Get(path: '/api/v1/tour-plans/{id}', summary: 'Détails d\'un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Détails du plan'), new OA\Response(response: 404, description: 'Non trouvé')]
    )]
    public function show(string $id)
    {
        return new TourPlanResource(TourPlan::with(['delegue', 'validePar', 'activities'])->findOrFail($id));
    }

    #[OA\Put(path: '/api/v1/tour-plans/{id}', summary: 'Mettre à jour un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'nom', type: 'string'),
            new OA\Property(property: 'statut', type: 'string', enum: ['brouillon','soumis','valide','en_cours','termine','rejete']),
            new OA\Property(property: 'commentaire_validation', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Plan mis à jour')]
    )]
    public function update(UpdateTourPlanRequest $request, string $id)
    {
        $plan = TourPlan::findOrFail($id);
        $data = $request->validated();
        if (isset($data['statut']) && in_array($data['statut'], ['valide', 'rejete'])) {
            $data['valide_par'] = auth()->id();
            $data['valide_le'] = now();
        }
        $plan->update($data);
        return new TourPlanResource($plan->load(['delegue', 'validePar']));
    }

    #[OA\Delete(path: '/api/v1/tour-plans/{id}', summary: 'Supprimer un plan de tournée', security: [['bearerAuth' => []]], tags: ['TourPlans'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function destroy(string $id)
    {
        $tourPlan = TourPlan::findOrFail($id);
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
            'optimized_activities'       => $ordered->values(),
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
