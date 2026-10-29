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
        $query = Campaign::with(['products', 'country']);
        \App\Support\CountryFilter::apply($query);
        return response()->json($query->get());
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
        if (!$request->filled('country_id')) {
            $resolved = \App\Support\CountryFilter::resolveCountryId() ?? auth()->user()?->country_id;
            if ($resolved) {
                $request->merge(['country_id' => $resolved]);
            }
        }

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
            'statut' => 'nullable|string|in:brouillon,validation,active,terminee,suspendue,creation,deploiement,cloture',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $campaign = Campaign::create($validated);

        if (!empty($validated['product_ids'])) {
            $campaign->products()->sync($validated['product_ids']);
        }

        return response()->json($campaign->load(['products', 'country']), 201);
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
        return response()->json(Campaign::with(['products', 'country', 'activities'])->findOrFail($id));
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
            'statut' => 'sometimes|string|in:brouillon,validation,active,terminee,suspendue,creation,deploiement,cloture',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $campaign->update($validated);

        if (isset($validated['product_ids'])) {
            $campaign->products()->sync($validated['product_ids']);
        }

        return response()->json($campaign->load(['products', 'country']));
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

    #[OA\Patch(
        path: '/api/v1/campaigns/{id}/status',
        summary: 'Mettre à jour le statut d\'une campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'statut', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour')
        ]
    )]
    public function updateStatus(Request $request, string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $validated = $request->validate([
            'statut' => 'required|string|in:brouillon,validation,active,terminee,suspendue,creation,deploiement,cloture'
        ]);
        $campaign->update($validated);
        return response()->json($campaign);
    }

    #[OA\Post(
        path: '/api/v1/campaigns/{id}/products',
        summary: 'Ajouter un produit à la campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'product_id', type: 'string', format: 'uuid')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Produit ajouté')
        ]
    )]
    public function attachProduct(Request $request, string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id'
        ]);
        $campaign->products()->syncWithoutDetaching([$validated['product_id']]);
        return response()->json($campaign->load('products'));
    }

    #[OA\Delete(
        path: '/api/v1/campaigns/{id}/products/{product_id}',
        summary: 'Retirer un produit de la campagne',
        security: [['bearerAuth' => []]],
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'product_id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Produit retiré')
        ]
    )]
    public function detachProduct(string $id, string $product_id)
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->products()->detach($product_id);
        return response()->json($campaign->load('products'));
    }


}
