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

        // Notification manager
        $user = auth()->user();
        $manager = $user?->manager ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager', 'Superviseur', 'dir-commercial']))->first();
        if ($manager) {
            app(\App\Services\NotificationService::class)->send(
                $manager,
                'Planning de tournée soumis',
                "Le délégué {$user?->name} a créé le plan de tournée '{$plan->nom}'.",
                'planning',
                ['tour_plan_id' => $plan->id]
            );
        }

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
        TourPlan::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
