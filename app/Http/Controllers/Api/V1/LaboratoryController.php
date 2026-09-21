<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LaboratoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/laboratories',
        summary: 'Lister les laboratoires',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des laboratoires')
        ]
    )]
    public function index()
    {
        return response()->json(Laboratory::all());
    }

    #[OA\Post(
        path: '/api/v1/laboratories',
        summary: 'Créer un laboratoire',
        security: [['bearerAuth' => []]],
        tags: ['Laboratories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Pfizer'),
                    new OA\Property(property: 'contact_email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Laboratoire créé')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:laboratories',
            'contact_email' => 'nullable|email',
            'is_active' => 'boolean'
        ]);

        return response()->json(Laboratory::create($validated), 201);
    }
}
