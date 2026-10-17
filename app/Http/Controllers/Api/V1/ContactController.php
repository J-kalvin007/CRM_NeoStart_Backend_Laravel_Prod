<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Account;
use App\Models\Potential;
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
        $query = Contact::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with([
                // L'account doit être chargé sans le DynamicPerimeterScope car un établissement
                // peut appartenir à un autre utilisateur (ex: admin) que le délégué qui consulte.
                'account'  => fn($q) => $q->withoutGlobalScopes(),
                'accounts' => fn($q) => $q->withoutGlobalScopes(),
                'categories', 'speciality', 'contactType', 'potential', 'influence', 'segmentation', 'delegue',
            ])
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

        $user = auth()->user();
        if ($user) {
            $isGlobalAdminLocal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin', 'super-admin', 'Administrateur Groupe', 'Administrateur Pays', 'Direction Générale']);
            $isSupervisor = $user->hasAnyRole(['superviseur', 'Manager', 'supervisor', 'manager', 'directeur-commercial', 'directeur_commercial', 'directeur-marketing', 'directeur_marketing', 'sales_manager']);
            
            if ($isGlobalAdminLocal) {
                $requestedCountryId = $request->header('X-Country-Id');
                if ($requestedCountryId && $requestedCountryId !== 'group' && $requestedCountryId !== 'default') {
                    $query->whereHas('user', fn($q) => $q->where('country_id', $requestedCountryId));
                }
            } else {
                if ($isSupervisor) {
                    $teamMemberIds = array_merge([$user->id], $user->teamMemberIds());
                    $query->whereIn('contacts.user_id', $teamMemberIds);
                } else {
                    // Délégué médical : il ne voit STRICTEMENT que ses propres prescripteurs
                    $query->where('contacts.user_id', $user->id);
                }
            }

            if (!$user->isAdminOrSupervisor()) {
                $query->where(function ($sq) use ($user) {
                    $sq->whereIn('statut', ['actif', 'valide'])
                       ->orWhereIn('statut_validation', ['actif', 'valide'])
                       ->orWhere('contacts.user_id', $user->id);
                });
            }
        }

        $perPage = $request->get('per_page', 500);
        return ContactResource::collection($query->orderBy('created_at', 'desc')->paginate($perPage));
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
            'nom'        => 'nullable|string',
            'prenom'     => 'nullable|string',
            'telephone'  => 'nullable|string',
            'email'      => 'nullable|email',
            'exclude_id' => 'nullable|string', // ID du contact à exclure (utile lors d'une édition)
        ]);

        $countryId = null;
        if ($request->account_id) {
            $account = Account::with('country')->find($request->account_id);
            $countryId = $account?->country_id;
        }

        $excludeId = $request->exclude_id ?: null;
        $duplicate = $duplicateService->findDuplicateContact($countryId, $request->all(), $excludeId);

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

        $user = auth()->user();
        if ($user && !$user->isAdminOrSupervisor()) {
            $validated['statut'] = 'en_attente';
        } else {
            $validated['statut'] = 'valide';
        }

        if (!empty($validated['specialite'])) {
            $meta = $validated['meta'] ?? [];
            $meta['specialite'] = $validated['specialite'];
            $validated['meta'] = $meta;
            unset($validated['specialite']);
        }

        // Mapper potentiel (string) -> potential_id (UUID FK)
        if (!empty($validated['potentiel'])) {
            $codeMap = ['faible' => 'LOW', 'moyen' => 'MED', 'fort' => 'HIGH'];
            $code = $codeMap[strtolower($validated['potentiel'])] ?? null;
            if ($code) {
                $potential = Potential::where('code', $code)->first();
                if ($potential) {
                    $validated['potential_id'] = $potential->id;
                }
            }
            unset($validated['potentiel']);
        }

        // Résolution robuste des comptes et du compte principal
        $accountData = $this->resolveAccountData($request, $validated);

        // Validation dynamique
        if (!empty($validated['contact_type_id'])) {
            $type = ContactType::find($validated['contact_type_id']);
            $validationService->validateMeta($validated['meta'] ?? null, $type);
        }

        // Détection de doublons (Bloque la requête si doublon)
        $countryId = null;
        if (!empty($validated['account_id'])) {
            $account = Account::with('country')->find($validated['account_id']);
            $countryId = $account?->country_id;
        }
        $duplicateService->abortIfDuplicateContact($countryId, $validated);

        // Retirer les attributs virtuels non-colonnes de la table contacts avant create
        unset(
            $validated['accounts'],
            $validated['account_ids'],
            $validated['attached_account_ids'],
            $validated['primary_account_id'],
            $validated['etablissement'],
            $validated['account_name'],
            $validated['attached_account_names']
        );

        $user = auth()->user();
        $isDelegue = $user && !$user->isAdminOrSupervisor();
        $statutValidation = $isDelegue ? 'en_attente' : 'valide';

        $contact = Contact::create(array_merge(
            $validated,
            [
                'user_id' => $user->id ?? null,
                'statut_validation' => $statutValidation,
            ]
        ));

        // Synchroniser la table pivot et le compte principal
        $this->syncContactAccounts($contact, $accountData);

        if ($isDelegue) {
            $supervisors = \App\Models\User::role('superviseur')->get();
            if ($supervisors->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($supervisors, new \App\Notifications\PendingValidationNotification($contact, $user));
            }
            $manager = $user?->manager ?? \App\Models\User::where('email', 'cmoucharif@gmail.com')->first() ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager']))->first();
            if ($manager) {
                NotificationController::sendNotification(
                    $manager,
                    'Nouvelle demande de validation prescripteur',
                    "Le délégué {$user->name} a créé le prescripteur {$contact->titre} {$contact->prenom} {$contact->nom} en attente de validation.",
                    'validation',
                    'manager',
                    ['contact_id' => (string) $contact->id]
                );
            }
        }

        return new ContactResource($contact->load(['account', 'accounts', 'categories']));
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
        $contact = Contact::with([
                'account'    => fn($q) => $q->withoutGlobalScopes(),
                'accounts'   => fn($q) => $q->withoutGlobalScopes(),
                'categories',
                'delegue',
                'speciality',
                'activities' => fn($q) => $q->latest()->limit(10),
            ])->findOrFail($id);
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
        $mergedMeta = array_merge($contact->meta ?? [], $validated['meta'] ?? []);
        if ($typeId) {
            $type = ContactType::find($typeId);
            if ($type) {
                $validationService->validateMeta($mergedMeta, $type);
            }
        }
        $validated['meta'] = $mergedMeta;

        // Mapper potentiel (string) -> potential_id (UUID FK)
        if (!empty($validated['potentiel'])) {
            $codeMap = ['faible' => 'LOW', 'moyen' => 'MED', 'fort' => 'HIGH'];
            $code = $codeMap[strtolower($validated['potentiel'])] ?? null;
            if ($code) {
                $potential = Potential::where('code', $code)->first();
                if ($potential) {
                    $validated['potential_id'] = $potential->id;
                }
            }
            unset($validated['potentiel']);
        }

        // Résolution robuste des comptes et du compte principal
        $accountData = $this->resolveAccountData($request, $validated);

        // Détection de doublons
        $accountId = $validated['account_id'] ?? $contact->account_id;
        $countryId = null;
        if ($accountId) {
            $account = Account::with('country')->find($accountId);
            $countryId = $account?->country_id;
        }
        $dataForDuplicateCheck = array_merge($contact->toArray(), $validated);
        $duplicateService->abortIfDuplicateContact($countryId, $dataForDuplicateCheck, $contact->id);

        // Retirer les attributs virtuels non-colonnes de la table contacts avant update
        unset(
            $validated['accounts'],
            $validated['account_ids'],
            $validated['attached_account_ids'],
            $validated['primary_account_id'],
            $validated['etablissement'],
            $validated['account_name'],
            $validated['attached_account_names']
        );

        $contact->update($validated);
        $this->syncContactAccounts($contact, $accountData);

        return new ContactResource($contact->load(['account', 'accounts', 'categories']));
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
        $record = Contact::findOrFail($id);
        abort_unless(auth()->user()->canDeleteSharedRecord($record->user_id, $record->statut_validation), 403,
            "Un prescripteur validé ne peut être supprimé que par votre superviseur ou l'administration.");
        $record->delete();

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
            'statut_validation' => 'valide',
        ]);

        if (class_exists(\App\Models\ActivityLog::class)) {
            try {
                \App\Models\ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'arbitrage',
                    'description' => "Arbitrage portefeuille : réaffectation prescripteur {$removedContact->id} au bassin régional, attribution de {$newContact->id} au délégué {$validated['delegate_id']}." . (!empty($validated['reason']) ? " Motif : {$validated['reason']}" : ""),
                ]);
            } catch (\Throwable $e) {}
        }

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

    /**
     * Suppression en masse de contacts (soft-delete)
     * DELETE /api/v1/contacts/bulk-delete
     * Body: { "ids": ["uuid1", "uuid2", ...] }
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required',
            'user_id' => 'nullable|exists:users,id',
            'account_id' => 'nullable|exists:accounts,id',
            'speciality_id' => 'nullable|exists:specialities,id',
            'statut' => 'nullable|string',
        ]);

        $updateData = array_filter([
            'user_id' => $request->user_id,
            'account_id' => $request->account_id,
            'speciality_id' => $request->speciality_id,
            'statut' => $request->statut,
        ], fn($v) => !is_null($v));

        if (!empty($updateData)) {
            Contact::withoutGlobalScopes()->whereIn('id', $request->ids)->update($updateData);
        }

        return response()->json([
            'message' => 'Mise à jour en masse des prescripteurs effectuée avec succès.',
            'updated_count' => count($request->ids),
        ]);
    }

    public function bulkDelete(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1|max:500',
            'ids.*' => 'required|uuid|exists:contacts,id',
        ]);

        $user = auth()->user();
        $ids  = $request->input('ids');

        // Seuls les admins/superviseurs peuvent supprimer en masse
        // Les délégués peuvent seulement supprimer leurs propres contacts
        $query = Contact::withoutGlobalScopes()->whereIn('id', $ids);

        if (!$user->isAdminOrSupervisor()) {
            $query->where('user_id', $user->id);
        }

        $contacts = $query->get();
        $deletedCount = 0;

        foreach ($contacts as $contact) {
            $contact->delete();
            $deletedCount++;
        }

        $skipped = count($ids) - $deletedCount;

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} prescripteur(s) supprimé(s) avec succès.",
            'deleted' => $deletedCount,
            'skipped' => $skipped,
        ]);
    }

    public function validateContact(Request $request, string $id)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $user = auth()->user();

        if ($user && $user->hasRole('delegue_medical')) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $contact->update([
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        return response()->json([
            'message' => 'Prescripteur validé avec succès.',
            'data'    => new ContactResource($contact->load(['account', 'categories']))
        ]);
    }

    public function rejectContact(Request $request, string $id)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $user = auth()->user();

        if ($user && $user->hasRole('delegue_medical')) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du rejet est obligatoire.'
        ]);

        $contact->update([
            'statut_validation' => 'rejete',
            'statut'            => 'inactif',
            'rejection_reason'  => $validated['motif'],
        ]);

        return response()->json([
            'message' => 'Prescripteur rejeté avec enregistrement du motif.',
            'data'    => new ContactResource($contact->load(['account', 'categories']))
        ]);
    }

    /**
     * Résout l'ID du compte principal et les comptes liés depuis divers formats d'entrée
     * (account_id, accounts, account_ids, attached_account_ids, etablissement, account_name, attached_account_names, meta)
     */
    private function resolveAccountData(Request $request, array &$validated): array
    {
        $resolvedAccountIds = [];
        $primaryAccountId = $validated['primary_account_id'] ?? $request->input('primary_account_id');

        // 1. Collecter tous les IDs d'établissements potentiels
        $potentialIds = array_merge(
            !empty($validated['account_id']) ? [$validated['account_id']] : (!empty($request->account_id) ? [$request->account_id] : []),
            is_array($request->input('accounts')) ? $request->input('accounts') : [],
            is_array($request->input('account_ids')) ? $request->input('account_ids') : [],
            is_array($request->input('attached_account_ids')) ? $request->input('attached_account_ids') : []
        );

        foreach ($potentialIds as $item) {
            if (is_string($item) && \Illuminate\Support\Str::isUuid($item)) {
                $resolvedAccountIds[] = $item;
            }
        }

        // 2. Collecter tous les noms d'établissements potentiels
        $potentialNames = array_merge(
            !empty($validated['etablissement']) ? [$validated['etablissement']] : (!empty($request->etablissement) ? [$request->etablissement] : []),
            !empty($validated['account_name']) ? [$validated['account_name']] : (!empty($request->account_name) ? [$request->account_name] : []),
            is_array($request->input('attached_account_names')) ? $request->input('attached_account_names') : [],
            !empty($validated['meta']['etablissement']) ? [$validated['meta']['etablissement']] : [],
            is_array($validated['meta']['attached_account_names'] ?? null) ? $validated['meta']['attached_account_names'] : []
        );

        // Si account_id passé n'est pas un UUID mais un nom (ex: "CHU TEST 3")
        if (!empty($validated['account_id']) && !\Illuminate\Support\Str::isUuid($validated['account_id'])) {
            $potentialNames[] = $validated['account_id'];
            unset($validated['account_id']);
        }

        foreach ($potentialNames as $name) {
            if (is_string($name) && trim($name) !== '' && trim($name) !== 'Non rattaché') {
                $cleanName = trim($name);
                // Rechercher le compte par nom exact ou approchant
                $matchedAccount = Account::withoutGlobalScopes()
                    ->where('nom', $cleanName)
                    ->orWhere('nom', 'like', "%$cleanName%")
                    ->first();
                if ($matchedAccount) {
                    $resolvedAccountIds[] = $matchedAccount->id;
                    $primaryAccountId ??= $matchedAccount->id;
                }
            }
        }

        $resolvedAccountIds = array_values(array_unique(array_filter($resolvedAccountIds, fn($id) => \Illuminate\Support\Str::isUuid($id))));

        // Définir le compte principal
        if ($primaryAccountId && in_array($primaryAccountId, $resolvedAccountIds)) {
            $validated['account_id'] = $primaryAccountId;
        } elseif (!empty($resolvedAccountIds)) {
            $validated['account_id'] = $resolvedAccountIds[0];
            $primaryAccountId = $resolvedAccountIds[0];
        }

        // Mettre à jour meta avec etablissement et attached_account_names pour rétrocompatibilité et robustesse
        if (!empty($validated['account_id'])) {
            $primaryAccount = Account::withoutGlobalScopes()->find($validated['account_id']);
            if ($primaryAccount) {
                $meta = $validated['meta'] ?? [];
                $meta['etablissement'] = $primaryAccount->nom;
                $allNames = Account::withoutGlobalScopes()->whereIn('id', $resolvedAccountIds)->pluck('nom')->toArray();
                $meta['attached_account_names'] = !empty($allNames) ? $allNames : [$primaryAccount->nom];
                $validated['meta'] = $meta;
            }
        }

        return [
            'account_ids' => $resolvedAccountIds,
            'primary_account_id' => $primaryAccountId,
        ];
    }

    private function syncContactAccounts(Contact $contact, array $accountData): void
    {
        $accountIds = $accountData['account_ids'] ?? [];
        $primaryAccountId = $accountData['primary_account_id'] ?? $contact->account_id;

        if (!empty($accountIds)) {
            $syncData = [];
            foreach ($accountIds as $accId) {
                $isPrimary = $primaryAccountId ? ($accId === $primaryAccountId) : ($accId === $accountIds[0]);
                $syncData[$accId] = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'is_primary' => $isPrimary,
                ];
            }
            $contact->accounts()->sync($syncData);

            if (!$contact->account_id || $contact->account_id !== $primaryAccountId) {
                $contact->account_id = $primaryAccountId ?? $accountIds[0];
                $contact->saveQuietly();
            }
        } elseif ($contact->account_id) {
            $contact->accounts()->sync([
                $contact->account_id => [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'is_primary' => true,
                ]
            ]);
        }
    }
}
