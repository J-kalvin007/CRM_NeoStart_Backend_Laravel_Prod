<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CampaignController extends Controller
{
    #[OA\Get(
        path: '/api/v1/campaigns',
        summary: 'Lister les campagnes',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des campagnes')
        ]
    )]
    public function index()
    {
        return response()->json(Campaign::with(['products', 'teams'])->get());
    }

    #[OA\Post(
        path: '/api/v1/campaigns',
        summary: 'Créer une campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'date_debut', 'date_fin', 'country_id'],
                properties: [
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                    new OA\Property(property: 'budget', type: 'number', format: 'float', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Campagne créée')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|uuid|exists:countries,id',
            'nom' => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'cible' => 'nullable|string',
            'message' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'objectifs' => 'nullable|array',
            'meta' => 'nullable|array',
            'statut' => 'nullable|string|in:creation,validation,deploiement,execution,suivi,analyse,cloture',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'uuid|exists:teams,id',
        ]);

        $campaign = Campaign::create($validated);

        if (!empty($validated['product_ids'])) {
            $campaign->products()->sync($validated['product_ids']);
        }
        if (!empty($validated['team_ids'])) {
            $campaign->teams()->sync($validated['team_ids']);
        }

        return response()->json($campaign->load(['products', 'teams']), 201);
    }

    #[OA\Get(
        path: '/api/v1/campaigns/{id}',
        summary: 'Détails d\'une campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de la campagne'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function show(string $id)
    {
        return response()->json(Campaign::with(['products', 'teams', 'activities'])->findOrFail($id));
    }

    #[OA\Put(
        path: '/api/v1/campaigns/{id}',
        summary: 'Mettre à jour une campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'statut', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Campagne mise à jour'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
            'cible' => 'nullable|string',
            'message' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'objectifs' => 'nullable|array',
            'meta' => 'nullable|array',
            'statut' => 'sometimes|string|in:creation,validation,deploiement,execution,suivi,analyse,cloture',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'uuid|exists:teams,id',
        ]);

        $campaign->update($validated);

        if (isset($validated['product_ids'])) {
            $campaign->products()->sync($validated['product_ids']);
        }
        if (isset($validated['team_ids'])) {
            $campaign->teams()->sync($validated['team_ids']);
        }

        return response()->json($campaign->load(['products', 'teams']));
    }

    #[OA\Delete(
        path: '/api/v1/campaigns/{id}',
        summary: 'Supprimer une campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé')
        ]
    )]
    public function destroy(string $id)
    {
        Campaign::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
