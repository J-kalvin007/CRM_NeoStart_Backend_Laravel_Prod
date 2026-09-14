<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Models\Country;

#[OA\Tag(name: 'Countries', description: 'Gestion des pays')]
class CountryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/countries',
        summary: 'Lister tous les pays',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des pays',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id',                  type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name',                type: 'string', example: 'Togo'),
                            new OA\Property(property: 'iso_code',            type: 'string', example: 'TG'),
                            new OA\Property(property: 'currency',            type: 'string', example: 'XOF', nullable: true),
                            new OA\Property(property: 'language',            type: 'string', example: 'fr', nullable: true),
                            new OA\Property(property: 'timezone',            type: 'string', example: 'Africa/Lome', nullable: true),
                            new OA\Property(property: 'regulatory_settings', type: 'object', nullable: true),
                            new OA\Property(property: 'status',              type: 'boolean', example: true),
                        ]
                    )
                )
            )
        ]
    )]
    public function index()
    {
        return response()->json(Country::all());
    }

    #[OA\Post(
        path: '/api/v1/countries',
        summary: 'Créer un pays',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'iso_code'],
                properties: [
                    new OA\Property(property: 'name',                type: 'string',  example: 'Togo'),
                    new OA\Property(property: 'iso_code',            type: 'string',  example: 'TG', description: 'Code ISO 3 lettres max'),
                    new OA\Property(property: 'currency',            type: 'string',  example: 'XOF', nullable: true),
                    new OA\Property(property: 'language',            type: 'string',  example: 'fr', nullable: true),
                    new OA\Property(property: 'timezone',            type: 'string',  example: 'Africa/Lome', nullable: true),
                    new OA\Property(property: 'regulatory_settings', type: 'object',  nullable: true),
                    new OA\Property(property: 'status',              type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pays créé avec succès'),
            new OA\Response(response: 422, description: 'Validation échouée (iso_code déjà existant, champs manquants...)'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'iso_code'            => 'required|string|max:3|unique:countries,iso_code',
            'currency'            => 'nullable|string|max:10',
            'language'            => 'nullable|string|max:10',
            'timezone'            => 'nullable|string|max:100',
            'regulatory_settings' => 'nullable|array',
            'status'              => 'boolean',
        ]);

        $country = Country::create($validated);

        return response()->json($country, 201);
    }

    #[OA\Get(
        path: '/api/v1/countries/{id}',
        summary: 'Afficher un pays',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du pays'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
        ]
    )]
    public function show(string $id)
    {
        $country = Country::with('subsidiaries')->findOrFail($id);
        return response()->json($country);
    }

    #[OA\Put(
        path: '/api/v1/countries/{id}',
        summary: 'Modifier un pays',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',                type: 'string'),
                    new OA\Property(property: 'iso_code',            type: 'string'),
                    new OA\Property(property: 'currency',            type: 'string',  nullable: true),
                    new OA\Property(property: 'language',            type: 'string',  nullable: true),
                    new OA\Property(property: 'timezone',            type: 'string',  nullable: true),
                    new OA\Property(property: 'regulatory_settings', type: 'object',  nullable: true),
                    new OA\Property(property: 'status',              type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pays mis à jour'),
            new OA\Response(response: 422, description: 'Validation échouée'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $country = Country::findOrFail($id);

        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'iso_code'            => 'sometimes|string|max:3|unique:countries,iso_code,' . $id,
            'currency'            => 'nullable|string|max:10',
            'language'            => 'nullable|string|max:10',
            'timezone'            => 'nullable|string|max:100',
            'regulatory_settings' => 'nullable|array',
            'status'              => 'boolean',
        ]);

        $country->update($validated);

        return response()->json($country);
    }

    #[OA\Delete(
        path: '/api/v1/countries/{id}',
        summary: 'Supprimer un pays',
        description: 'Supprime un pays. Attention : la suppression échoue si des filiales ou utilisateurs y sont rattachés.',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Pays supprimé'),
            new OA\Response(response: 409, description: 'Impossible de supprimer — des filiales ou utilisateurs y sont rattachés'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
        ]
    )]
    public function destroy(string $id)
    {
        $country = Country::findOrFail($id);

        // Vérifier qu'aucune filiale n'est encore rattachée à ce pays
        if ($country->subsidiaries()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce pays : des filiales y sont encore rattachées.'
            ], 409);
        }

        $country->delete();

        return response()->json(null, 204);
    }
}
