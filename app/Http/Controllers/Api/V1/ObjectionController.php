<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Objections', description: 'Référentiel des objections médicales')]
class ObjectionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/products/{product}/objections',
        summary: 'Récupérer le référentiel des objections pour un produit',
        security: [['bearerAuth' => []]],
        tags: ['Objections'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des objections associées au produit')
        ]
    )]
    public function indexByProduct(Product $product)
    {
        return response()->json($product->objections()->with('supportingDocument')->get());
    }

    #[OA\Post(
        path: '/api/v1/activities/{activity}/objections',
        summary: 'Enregistrer une objection soulevée lors d\'une visite',
        security: [['bearerAuth' => []]],
        tags: ['Objections'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['objection_id', 'physician_reaction'],
                properties: [
                    new OA\Property(property: 'objection_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'physician_reaction', type: 'string', enum: ['convaincu', 'partiellement_convaincu', 'refus']),
                    new OA\Property(property: 'notes', type: 'string', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Objection enregistrée avec succès')
        ]
    )]
    public function storeForActivity(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'objection_id' => 'required|uuid|exists:objections,id',
            'physician_reaction' => 'required|string|in:convaincu,partiellement_convaincu,refus',
            'notes' => 'nullable|string'
        ]);

        $activity->objections()->attach($validated['objection_id'], [
            'physician_reaction' => $validated['physician_reaction'],
            'notes' => $validated['notes'],
        ]);

        return response()->json(['message' => 'Objection enregistrée pour la visite avec succès.'], 200);
    }
}
