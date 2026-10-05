<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Models\Country;
use App\Http\Resources\CountryResource;

#[OA\Tag(name: 'Countries', description: 'Gestion des pays et réglementations nationales')]
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
                description: 'Liste des pays récupérée avec succès',
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
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function index()
    {
        return CountryResource::collection(Country::all());
    }

    #[OA\Post(
        path: '/api/v1/countries',
        summary: 'Créer un pays',
        description: 'Enregistre un nouveau pays avec ses paramètres réglementaires, sa monnaie, son fuseau horaire et son statut.',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'iso_code'],
                properties: [
                    new OA\Property(property: 'name',                type: 'string',  example: 'Togo'),
                    new OA\Property(property: 'iso_code',            type: 'string',  example: 'TG', description: 'Code ISO 2 ou 3 lettres unique'),
                    new OA\Property(property: 'currency',            type: 'string',  example: 'XOF', nullable: true),
                    new OA\Property(property: 'language',            type: 'string',  example: 'fr', nullable: true),
                    new OA\Property(property: 'timezone',            type: 'string',  example: 'Africa/Lome', nullable: true),
                    new OA\Property(property: 'regulatory_settings', type: 'object',  nullable: true),
                    new OA\Property(property: 'status',              type: 'boolean', example: true, default: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pays créé avec succès'),
            new OA\Response(response: 422, description: 'Validation échouée (iso_code déjà utilisé ou champs invalides)'),
            new OA\Response(response: 401, description: 'Non authentifié')
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

        return response()->json(new CountryResource($country), 201);
    }

    #[OA\Get(
        path: '/api/v1/countries/{id}',
        summary: 'Afficher les détails d\'un pays',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du pays avec ses filiales'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function show(string $id)
    {
        $country = Country::findOrFail($id);
        return new CountryResource($country);
    }

    #[OA\Put(
        path: '/api/v1/countries/{id}',
        summary: 'Modifier un pays',
        description: 'Met à jour les informations d\'un pays (nom, monnaie, langue, fuseau horaire, paramètres réglementaires, statut).',
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
            new OA\Response(response: 200, description: 'Pays mis à jour avec succès'),
            new OA\Response(response: 422, description: 'Validation échouée'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
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

        return new CountryResource($country);
    }

    #[OA\Delete(
        path: '/api/v1/countries/{id}',
        summary: 'Supprimer un pays',
        description: 'Supprime un pays. La suppression est protégée et rejetée si des filiales y sont encore rattachées.',
        security: [['bearerAuth' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pays supprimé avec succès'),
            new OA\Response(response: 409, description: 'Conflit : des filiales sont rattachées à ce pays'),
            new OA\Response(response: 404, description: 'Pays introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function destroy(string $id)
    {
        $country = Country::findOrFail($id);

        // Protection : vérifier qu'aucun compte n'est encore rattaché
        if ($country->accounts()->exists() || $country->users()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce pays : des données (comptes ou utilisateurs) y sont encore rattachées.'
            ], 409);
        }

        $country->delete();

        return response()->json([
            'message' => 'Pays supprimé avec succès.'
        ], 200);
    }
}
