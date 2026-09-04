<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ContactController extends Controller
{
    #[OA\Get(
        path: '/api/v1/contacts',
        summary: 'Lister tous les contacts (Médecins, Spécialistes, Interlocuteurs B2B)',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'specialite', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_kol', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'account_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif', 'inactif', 'archive'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Recherche par nom, prénom ou spécialité'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des contacts récupérée avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Contact::with(['account', 'categories'])
            ->when($request->specialite, fn($q, $v) => $q->where('specialite', 'like', "%$v%"))
            ->when($request->has('is_kol'), fn($q) => $q->where('is_kol', $request->boolean('is_kol')))
            ->when($request->account_id, fn($q, $v) => $q->where('account_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('prenom', 'like', "%$v%")
                  ->orWhere('specialite', 'like', "%$v%");
            }));

        return ContactResource::collection($query->paginate(25));
    }

    #[OA\Post(
        path: '/api/v1/contacts',
        summary: 'Créer un nouveau contact',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'nom', type: 'string', example: 'Koné'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Ibrahim'),
                    new OA\Property(property: 'titre', type: 'string', example: 'Dr.', nullable: true),
                    new OA\Property(property: 'specialite', type: 'string', example: 'Cardiologie', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'potentiel', type: 'string', enum: ['faible', 'moyen', 'fort'], nullable: true),
                    new OA\Property(property: 'influence', type: 'string', enum: ['faible', 'moyen', 'fort'], nullable: true),
                    new OA\Property(property: 'is_kol', type: 'boolean', default: false),
                    new OA\Property(property: 'rpps', type: 'string', nullable: true),
                    new OA\Property(property: 'gps_lat', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'gps_lng', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contact créé avec succès'),
            new OA\Response(response: 422, description: 'Données de validation invalides'),
        ]
    )]
    public function store(StoreContactRequest $request)
    {
        $contact = Contact::create(array_merge(
            $request->validated(),
            ['user_id' => auth()->id()]
        ));
        return new ContactResource($contact->load(['account', 'categories']));
    }

    #[OA\Get(
        path: '/api/v1/contacts/{id}',
        summary: 'Afficher les détails d\'un contact',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du contact'),
            new OA\Response(response: 404, description: 'Contact non trouvé'),
        ]
    )]
    public function show(string $id)
    {
        $contact = Contact::with(['account', 'categories', 'activities' => fn($q) => $q->latest()->limit(10)])->findOrFail($id);
        return new ContactResource($contact);
    }

    #[OA\Put(
        path: '/api/v1/contacts/{id}',
        summary: 'Mettre à jour un contact',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'specialite', type: 'string', nullable: true),
                    new OA\Property(property: 'potentiel', type: 'string', enum: ['faible', 'moyen', 'fort'], nullable: true),
                    new OA\Property(property: 'is_kol', type: 'boolean'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['actif', 'inactif', 'archive']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contact mis à jour avec succès'),
            new OA\Response(response: 404, description: 'Contact non trouvé'),
        ]
    )]
    public function update(UpdateContactRequest $request, string $id)
    {
        $contact = Contact::findOrFail($id);
        $contact->update($request->validated());
        return new ContactResource($contact->load(['account', 'categories']));
    }

    #[OA\Delete(
        path: '/api/v1/contacts/{id}',
        summary: 'Supprimer (soft-delete) un contact',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Contact supprimé avec succès'),
            new OA\Response(response: 404, description: 'Contact non trouvé'),
        ]
    )]
    public function destroy(string $id)
    {
        Contact::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
