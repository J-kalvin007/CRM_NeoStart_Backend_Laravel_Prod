<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ObjectifResource;
use App\Http\Requests\StoreObjectifRequest;
use App\Http\Requests\UpdateObjectifRequest;
use App\Models\Objectif;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ObjectifController extends Controller
{
    #[OA\Get(path: '/api/v1/objectifs', summary: 'Lister les objectifs de performance', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'team_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'type_objectif', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif','atteint','depasse','non_atteint','annule'])),
            new OA\Parameter(name: 'periode', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Ex: Q3-2026, Oct-2026'),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des objectifs')]
    )]
    public function index(Request $request)
    {
        $query = Objectif::with(['user', 'team', 'product'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->team_id, fn($q, $v) => $q->where('team_id', $v))
            ->when($request->type_objectif, fn($q, $v) => $q->where('type_objectif', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->periode, fn($q, $v) => $q->where('periode', 'like', "%$v%"));
        return ObjectifResource::collection($query->paginate(25));
    }

    #[OA\Post(path: '/api/v1/objectifs', summary: 'Créer un objectif de performance', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['type_objectif', 'valeur_cible', 'date_debut', 'date_fin'],
            properties: [
                new OA\Property(property: 'user_id', type: 'integer', nullable: true),
                new OA\Property(property: 'team_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'type_objectif', type: 'string', enum: ['nombre_visites','couverture_contacts','ca_ventes','nb_commandes','nb_echantillons','nb_nouveaux_contacts','nb_activites_kol','campagne','autre']),
                new OA\Property(property: 'valeur_cible', type: 'number', format: 'float', example: 100),
                new OA\Property(property: 'unite', type: 'string', example: 'visites', nullable: true),
                new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                new OA\Property(property: 'periode', type: 'string', example: 'Q3-2026', nullable: true),
                new OA\Property(property: 'product_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Objectif créé avec succès')]
    )]
    public function store(StoreObjectifRequest $request)
    {
        $objectif = Objectif::create($request->validated());
        return new ObjectifResource($objectif->load(['user', 'team', 'product']));
    }

    #[OA\Get(path: '/api/v1/objectifs/{id}', summary: 'Détails d\'un objectif', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Détails de l\'objectif'), new OA\Response(response: 404, description: 'Non trouvé')]
    )]
    public function show(string $id)
    {
        return new ObjectifResource(Objectif::with(['user', 'team', 'product'])->findOrFail($id));
    }

    #[OA\Put(path: '/api/v1/objectifs/{id}', summary: 'Mettre à jour un objectif (valeur réalisée)', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'valeur_realisee', type: 'number', format: 'float'),
            new OA\Property(property: 'statut', type: 'string', enum: ['actif','atteint','depasse','non_atteint','annule']),
        ])),
        responses: [new OA\Response(response: 200, description: 'Objectif mis à jour')]
    )]
    public function update(UpdateObjectifRequest $request, string $id)
    {
        $objectif = Objectif::findOrFail($id);
        $objectif->update($request->validated());
        $objectif->recalculerTaux();
        return new ObjectifResource($objectif->load(['user', 'team', 'product']));
    }

    #[OA\Delete(path: '/api/v1/objectifs/{id}', summary: 'Supprimer un objectif', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function destroy(string $id)
    {
        Objectif::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
