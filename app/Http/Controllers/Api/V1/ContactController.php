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
        $query = Contact::with([
                // L'account doit être chargé sans le DynamicPerimeterScope car un établissement
                // peut appartenir à un autre utilisateur (ex: admin) que le délégué qui consulte.
                // withoutGlobalScopes() sur la relation garantit que le nom s'affiche toujours.
                'account' => fn($q) => $q->withoutGlobalScopes(),
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
            $isGlobalAdminLocal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin', 'Administrateur Groupe', 'Administrateur Pays', 'Direction Générale']);
            $isSupervisor = $user->hasAnyRole(['superviseur', 'Manager', 'supervisor', 'manager', 'directeur-commercial', 'directeur_commercial', 'directeur-marketing', 'directeur_marketing']);
            
            if (!$isGlobalAdminLocal) {
                if ($isSupervisor) {
                    $subordinateIds = $user->subordinates()->pluck('id')->toArray();
                    $subordinateIds[] = $user->id;
                    $query->whereIn('contacts.user_id', $subordinateIds);
                } elseif ($user->hasRole('delegue_medical')) {
                    $query->where('contacts.user_id', $user->id);
                }
            }

            if (!$user->isAdminOrSupervisor()) {
                $query->whereIn('statut', ['actif', 'valide']);
            }
        }

        // Filtrage additionnel par périmètre organisationnel sélectionné (header X-Organizational-Unit-Id)
        $perimeterUnitId = app()->bound('active_perimeter_unit_id') ? app('active_perimeter_unit_id') : null;
        $isGlobalAdmin   = $user && ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']));

        if ($perimeterUnitId && $user && !$isGlobalAdmin) {
            // Utilisateurs non-admin : filtre strict par UserScope dans le périmètre
            $allUnitIds = $this->getDescendantUnitIds([$perimeterUnitId]);
            $userIdsInUnit = \Illuminate\Support\Facades\DB::table('user_scopes')
                ->whereIn('organizational_unit_id', $allUnitIds)
                ->where('is_active', true)
                ->pluck('user_id')
                ->unique()
                ->toArray();
            if (!empty($userIdsInUnit)) {
                $query->whereIn('contacts.user_id', $userIdsInUnit);
            }
        }
        // Pour le superadmin : le périmètre topbar est informatif seulement
        // → il voit TOUS les contacts sans restriction supplémentaire

        $perPage = $request->get('per_page', 500);
        return ContactResource::collection($query->paginate($perPage));
    }

    /**
     * Récupère tous les IDs descendants récursivement
     */
    private function getDescendantUnitIds(array $parentIds): array
    {
        if (empty($parentIds)) return [];
        $allIds = $parentIds;
        $currentParentIds = $parentIds;
        while (true) {
            $childrenIds = \Illuminate\Support\Facades\DB::table('organizational_units')
                ->whereIn('parent_id', $currentParentIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
            if (empty($childrenIds)) break;
            $allIds = array_merge($allIds, $childrenIds);
            $currentParentIds = $childrenIds;
        }
        return array_unique($allIds);
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
            $account = Account::with('organizationalUnit')->find($request->account_id);
            // $countryId = $account?->organizationalUnit?->country_id; // TODO: Adapt if organizational units have a country mapping
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

        // Validation dynamique
        if (!empty($validated['contact_type_id'])) {
            $type = ContactType::find($validated['contact_type_id']);
            $validationService->validateMeta($validated['meta'] ?? null, $type);
        }

        // Détection de doublons (Bloque la requête si doublon)
        $countryId = null;
        if (!empty($validated['account_id'])) {
            $account = Account::with('organizationalUnit')->find($validated['account_id']);
            // $countryId = $account?->organizationalUnit?->country_id;
        }
        $duplicateService->abortIfDuplicateContact($countryId, $validated);

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

        $accountsInput = $request->input('accounts') ?? $request->input('account_ids') ?? null;
        if (!empty($accountsInput)) {
            $accountIds = (array) $accountsInput;
            $primaryAccountId = $request->input('primary_account_id');
            $syncData = [];
            foreach ($accountIds as $index => $accId) {
                $isPrimary = $primaryAccountId ? ($accId === $primaryAccountId) : ($index === 0);
                $syncData[$accId] = ['id' => \Illuminate\Support\Str::uuid()->toString(), 'is_primary' => $isPrimary];
            }
            $contact->accounts()->sync($syncData);
        } elseif ($contact->account_id) {
            $contact->accounts()->sync([
                $contact->account_id => ['id' => \Illuminate\Support\Str::uuid()->toString(), 'is_primary' => true]
            ]);
        }

        if ($isDelegue) {
            $supervisors = \App\Models\User::role('superviseur')->where('team_id', $user->team_id)->get();
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
        $contact = Contact::with([
                'account'    => fn($q) => $q->withoutGlobalScopes(),
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

        // Détection de doublons
        $accountId = $validated['account_id'] ?? $contact->account_id;
        $countryId = null;
        if ($accountId) {
            $account = Account::with('organizationalUnit')->find($accountId);
            // $countryId = $account?->organizationalUnit?->country_id;
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

    /**
     * Suppression en masse de contacts (soft-delete)
     * DELETE /api/v1/contacts/bulk-delete
     * Body: { "ids": ["uuid1", "uuid2", ...] }
     */
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
}
