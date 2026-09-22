<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DigitalSupport;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DigitalSupportController extends Controller
{
    #[OA\Get(
        path: '/api/v1/digital-supports',
        summary: 'Lister les supports ADV (E-Detailing)',
        security: [['bearerAuth' => []]],
        tags: ['Digital Supports'],
        responses: [
            new OA\Response(response: 200, description: 'Liste récupérée avec succès'),
        ]
    )]
    public function index()
    {
        return response()->json(DigitalSupport::paginate(25));
    }

    #[OA\Post(
        path: '/api/v1/digital-supports',
        summary: 'Créer un nouveau support digital',
        security: [['bearerAuth' => []]],
        tags: ['Digital Supports'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'product_id', 'file_path'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'version', type: 'string'),
                    new OA\Property(property: 'total_slides', type: 'integer'),
                    new OA\Property(property: 'file_path', type: 'string'),
                    new OA\Property(property: 'thumbnail_url', type: 'string'),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Support créé avec succès'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'version' => 'nullable|string',
            'total_slides' => 'nullable|integer',
            'file_path' => 'required|string',
            'thumbnail_url' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $support = DigitalSupport::create($validated);
        return response()->json($support, 201);
    }

    #[OA\Get(
        path: '/api/v1/digital-supports/{id}',
        summary: 'Afficher les détails d\'un support digital',
        security: [['bearerAuth' => []]],
        tags: ['Digital Supports'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du support'),
        ]
    )]
    public function show(DigitalSupport $digitalSupport)
    {
        return response()->json($digitalSupport);
    }

    #[OA\Put(
        path: '/api/v1/digital-supports/{id}',
        summary: 'Mettre à jour un support digital',
        security: [['bearerAuth' => []]],
        tags: ['Digital Supports'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Support mis à jour'),
        ]
    )]
    public function update(Request $request, DigitalSupport $digitalSupport)
    {
        $digitalSupport->update($request->all());
        return response()->json($digitalSupport);
    }

    #[OA\Delete(
        path: '/api/v1/digital-supports/{id}',
        summary: 'Supprimer un support digital',
        security: [['bearerAuth' => []]],
        tags: ['Digital Supports'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Support supprimé'),
        ]
    )]
    public function destroy(DigitalSupport $digitalSupport)
    {
        $digitalSupport->delete();
        return response()->json(null, 204);
    }
}
