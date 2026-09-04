<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KolProfile;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KolProfileController extends Controller
{
    #[OA\Get(
        path: '/api/v1/kol-profiles',
        summary: 'Lister les profils KOL',
        security: [['bearerAuth' => []]],
        tags: ['KOL Profiles'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des profils KOL')
        ]
    )]
    public function index()
    {
        return response()->json(KolProfile::with('contact')->get());
    }

    #[OA\Post(
        path: '/api/v1/kol-profiles',
        summary: 'Créer un profil KOL (Extension d\'un contact)',
        security: [['bearerAuth' => []]],
        tags: ['KOL Profiles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['contact_id'],
                properties: [
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'expertise', type: 'string'),
                    new OA\Property(property: 'influence_institution', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Profil KOL créé'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|uuid|exists:contacts,id|unique:kol_profiles,contact_id',
            'expertise' => 'nullable|string|max:255',
            'influence_institution' => 'nullable|string|max:255',
            'publications' => 'nullable|string',
            'relations_historique' => 'nullable|string',
            'engagement' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        $kolProfile = KolProfile::create($validated);

        return response()->json($kolProfile->load('contact'), 201);
    }

    #[OA\Get(
        path: '/api/v1/kol-profiles/{id}',
        summary: 'Détails d\'un profil KOL',
        security: [['bearerAuth' => []]],
        tags: ['KOL Profiles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du profil KOL'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function show(string $id)
    {
        return response()->json(KolProfile::with('contact')->findOrFail($id));
    }

    #[OA\Put(
        path: '/api/v1/kol-profiles/{id}',
        summary: 'Mettre à jour un profil KOL',
        security: [['bearerAuth' => []]],
        tags: ['KOL Profiles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'expertise', type: 'string'),
                    new OA\Property(property: 'influence_institution', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil KOL mis à jour'),
            new OA\Response(response: 404, description: 'Non trouvé')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $kolProfile = KolProfile::findOrFail($id);

        $validated = $request->validate([
            'expertise' => 'nullable|string|max:255',
            'influence_institution' => 'nullable|string|max:255',
            'publications' => 'nullable|string',
            'relations_historique' => 'nullable|string',
            'engagement' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        $kolProfile->update($validated);

        return response()->json($kolProfile->load('contact'));
    }

    #[OA\Delete(
        path: '/api/v1/kol-profiles/{id}',
        summary: 'Supprimer un profil KOL (le contact reste intact)',
        security: [['bearerAuth' => []]],
        tags: ['KOL Profiles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé')
        ]
    )]
    public function destroy(string $id)
    {
        KolProfile::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
