<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Account;
use App\Services\DynamicValidationService;
use App\Services\DuplicateDetectionService;
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
        $query = Contact::with(['account', 'categories', 'speciality', 'contactType', 'potential', 'influence', 'segmentation'])
            ->when($request->specialite ?? $request->speciality, function($q, $v) {
                if (!$v || $v === 'Tous') return;
                $q->where(function($sub) use ($v) {
                    $sub->whereHas('speciality', fn($sq) => $sq->where('nom', 'like', "%$v%"))
                        ->orWhere('meta->specialite', 'like', "%$v%")
                        ->orWhere('titre', 'like', "%$v%");
                });
            })
            ->when($request->facility_type ?? $request->account_type ?? $request->etablissement, function($q, $v) {
                if (!$v || $v === 'Tous') return;
                $q->where(function($sub) use ($v) {
                    $sub->whereHas('account', function($aq) use ($v) {
                        $aq->where('type', 'like', "%$v%")
                           ->orWhere('nom', 'like', "%$v%");
                    })->orWhere('meta->etablissement', 'like', "%$v%");
                });
            })
            ->when($request->statut ?? $request->status, function($q, $v) {
                if (!$v || $v === 'Tous') return;
                $q->where('statut', 'like', "%$v%");
            })
            ->when($request->has('is_kol'), fn($q) => $q->where('is_kol', $request->boolean('is_kol')))
            ->when($request->account_id, fn($q, $v) => $q->where('account_id', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('prenom', 'like', "%$v%")
                  ->orWhere('titre', 'like', "%$v%")
                  ->orWhere('meta->specialite', 'like', "%$v%")
                  ->orWhereHas('speciality', fn($sq) => $sq->where('nom', 'like', "%$v%"))
                  ->orWhereHas('account', fn($aq) => $aq->where('nom', 'like', "%$v%"));
            }));

        $perPage = $request->get('per_page', 100);
        return ContactResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/api/v1/contacts/check-duplicates',
        summary: 'Vérifier l\'existence de doublons potentiels',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'account_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'nom', type: 'string', nullable: true),
                    new OA\Property(property: 'prenom', type: 'string', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Résultat de la recherche'),
        ]
    )]
    public function checkDuplicates(Request $request, DuplicateDetectionService $duplicateService)
    {
        $request->validate([
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'nom' => 'nullable|string',
            'prenom' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $countryId = null;
        if ($request->account_id) {
            $account = Account::with('subsidiary')->find($request->account_id);
            $countryId = $account?->subsidiary?->country_id;
        }

        $duplicate = $duplicateService->findDuplicateContact($countryId, $request->all());

        return response()->json([
            'is_duplicate' => $duplicate ? true : false,
            'duplicate' => $duplicate ? new ContactResource($duplicate->load(['account', 'categories'])) : null,
        ]);
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
    public function store(
        StoreContactRequest $request,
        DynamicValidationService $validationService,
        DuplicateDetectionService $duplicateService
    ) {
        $validated = $request->validated();

        // Validation dynamique
        if (!empty($validated['contact_type_id'])) {
            $type = ContactType::find($validated['contact_type_id']);
            $validationService->validateMeta($validated['meta'] ?? null, $type);
        }

        // Détection de doublons (Bloque la requête si doublon)
        $countryId = null;
        if (!empty($validated['account_id'])) {
            $account = Account::with('subsidiary')->find($validated['account_id']);
            $countryId = $account?->subsidiary?->country_id;
        }
        $duplicateService->abortIfDuplicateContact($countryId, $validated);

        $contact = Contact::create(array_merge(
            $validated,
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
    public function update(
        UpdateContactRequest $request,
        string $id,
        DynamicValidationService $validationService,
        DuplicateDetectionService $duplicateService
    ) {
        $contact = Contact::findOrFail($id);
        $validated = $request->validated();

        // Validation dynamique
        $typeId = $validated['contact_type_id'] ?? $contact->contact_type_id;
        if ($typeId) {
            $type = ContactType::find($typeId);
            $mergedMeta = array_merge($contact->meta ?? [], $validated['meta'] ?? []);
            $validationService->validateMeta($mergedMeta, $type);
        }

        // Détection de doublons
        $accountId = $validated['account_id'] ?? $contact->account_id;
        $countryId = null;
        if ($accountId) {
            $account = Account::with('subsidiary')->find($accountId);
            $countryId = $account?->subsidiary?->country_id;
        }
        $dataForDuplicateCheck = array_merge($contact->toArray(), $validated);
        $duplicateService->abortIfDuplicateContact($countryId, $dataForDuplicateCheck, $contact->id);

        $contact->update($validated);
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

    #[OA\Post(
        path: '/api/v1/contacts/arbitrage',
        summary: 'Arbitrage du portefeuille (Jauge 100 max)',
        security: [['bearerAuth' => []]],
        tags: ['Contacts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delegate_id', 'new_contact_id', 'removed_contact_id'],
                properties: [
                    new OA\Property(property: 'delegate_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'new_contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'removed_contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'reason', type: 'string'),
                    new OA\Property(property: 'action', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Arbitrage effectué avec succès'),
        ]
    )]
    public function arbitrage(Request $request)
    {
        $validated = $request->validate([
            'delegate_id' => 'required|uuid|exists:users,id',
            'new_contact_id' => 'required|uuid|exists:contacts,id',
            'removed_contact_id' => 'required|uuid|exists:contacts,id',
            'reason' => 'nullable|string',
            'action' => 'nullable|string',
        ]);

        $removedContact = Contact::findOrFail($validated['removed_contact_id']);
        $newContact = Contact::findOrFail($validated['new_contact_id']);

        // Réaffecter l'ancien prescripteur au bassin régional (on lui enlève son délégué)
        $removedContact->update([
            'user_id' => null, 
            // On pourrait ajouter un champ history ou reason si le modèle le permet
        ]);

        // Assigner le nouveau prescripteur au délégué
        $newContact->update([
            'user_id' => $validated['delegate_id'],
            'statut_validation' => 'valide', // ou on garde 'en_attente'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Arbitrage effectué. Prescripteur réaffecté au bassin régional sans perte d\'historique.',
            'data' => [
                'delegate_id' => $validated['delegate_id'],
                'active_portfolio_count' => Contact::where('user_id', $validated['delegate_id'])->count(),
                'reallocated_contact' => [
                    'id' => $removedContact->id,
                    'previous_delegate' => $validated['delegate_id'],
                    'unassigned_at' => now()->toIso8601String(),
                ]
            ]
        ], 200);
    }
}
