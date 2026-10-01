<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Potential;
use App\Services\DynamicValidationService;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AccountController extends Controller
{
    #[OA\Get(
        path: '/api/v1/accounts',
        summary: 'Lister tous les comptes (Pharmacies, Hôpitaux, Grossistes...)',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(name: 'account_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pharmacie', 'hopital', 'clinique', 'grossiste', 'cabinet', 'entreprise', 'autre'])),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif', 'inactif', 'suspendu'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Recherche par nom, ville ou responsable'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des comptes récupérée avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Account::with(['organizationalUnit', 'categories', 'accountType', 'user', 'delegue'])
            ->when($request->account_type, fn($q, $v) => $q->where('account_type', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('ville', 'like', "%$v%")
                  ->orWhere('responsable', 'like', "%$v%");
            }));

        $user = auth()->user();
        if ($user && !$user->isAdminOrSupervisor()) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('statut', ['actif', 'valide'])
                  ->orWhere('accounts.user_id', $user->id)
                  ->orWhere('accounts.delegue_id', $user->id)
                  ->orWhere('accounts.created_by', $user->id);
            });
        }

        $perimeterUnitId = app()->bound('active_perimeter_unit_id') ? app('active_perimeter_unit_id') : null;
        $isGlobalAdmin   = $user && ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']));

        if ($perimeterUnitId && $user && !$isGlobalAdmin) {
            $allUnitIds = $this->getDescendantUnitIds([$perimeterUnitId]);
            $userIdsInUnit = \Illuminate\Support\Facades\DB::table('user_scopes')
                ->whereIn('organizational_unit_id', $allUnitIds)
                ->where('is_active', true)
                ->pluck('user_id')
                ->unique()
                ->toArray();

            if (!empty($userIdsInUnit)) {
                $query->where(function ($q) use ($userIdsInUnit) {
                    $q->whereIn('accounts.user_id', $userIdsInUnit)
                    // Établissements créés par un utilisateur du périmètre
                      ->orWhereIn('accounts.delegue_id', $userIdsInUnit)
                      ->orWhereIn('accounts.id', function ($sub) use ($userIdsInUnit) {
                          $sub->select('account_id')
                              ->from('contacts')
                              ->whereIn('user_id', $userIdsInUnit)
                              ->whereNotNull('account_id');
                      });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (!$isGlobalAdmin && !$perimeterUnitId) {
            // Pas de périmètre sélectionné mais non-admin : voir ses propres établissements + ceux de ses contacts
            $query->where(function ($q) use ($user) {
                $q->where('accounts.user_id', $user->id)
                  ->orWhereIn('accounts.id', function ($sub) use ($user) {
                      $sub->select('account_id')
                          ->from('contacts')
                          ->where('user_id', $user->id)
                          ->whereNotNull('account_id');
                  });
            });
        }

        // Ordre par défaut pour éviter les sauts de pagination lors des mises à jour
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 500);
        return AccountResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/api/v1/accounts/check-duplicates',
        summary: 'Vérifier l\'existence de doublons potentiels',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organizational_unit_id'],
                properties: [
                    new OA\Property(property: 'organizational_unit_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string', nullable: true),
                    new OA\Property(property: 'ville', type: 'string', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Résultat de la recherche de doublons'),
        ]
    )]
    public function checkDuplicates(Request $request, DuplicateDetectionService $duplicateService)
    {
        $request->validate([
            'organizational_unit_id' => 'required|uuid|exists:organizational_units,id',
            'nom' => 'nullable|string',
            'ville' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $duplicate = $duplicateService->findDuplicateAccount($request->organizational_unit_id, $request->all());

        return response()->json([
            'is_duplicate' => $duplicate ? true : false,
            'duplicate' => $duplicate ? new AccountResource($duplicate) : null,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/accounts',
        summary: 'Créer un nouveau compte',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'account_type'],
                properties: [
                    new OA\Property(property: 'organizational_unit_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'nom', type: 'string', example: 'Pharmacie Centrale'),
                    new OA\Property(property: 'account_type', type: 'string', enum: ['pharmacie', 'hopital', 'clinique', 'grossiste', 'cabinet', 'entreprise', 'autre']),
                    new OA\Property(property: 'adresse', type: 'string', nullable: true),
                    new OA\Property(property: 'ville', type: 'string', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'responsable', type: 'string', nullable: true),
                    new OA\Property(property: 'potentiel', type: 'string', enum: ['faible', 'moyen', 'fort'], nullable: true),
                    new OA\Property(property: 'gps_lat', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'gps_lng', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true, description: 'UUID généré côté mobile pour le mode offline'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé avec succès'),
            new OA\Response(response: 422, description: 'Données de validation invalides'),
        ]
    )]
    public function store(
        StoreAccountRequest $request,
        DynamicValidationService $validationService,
        DuplicateDetectionService $duplicateService
    ) {
        $validated = $request->validated();

        $user = auth()->user();
        
        if (empty($validated['organizational_unit_id']) && $user) {
            $primaryScope = \App\Models\UserScope::where('user_id', $user->id)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->first();
                
            if ($primaryScope) {
                $validated['organizational_unit_id'] = $primaryScope->organizational_unit_id;
            } else {
                $fallbackScope = \App\Models\UserScope::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();
                if ($fallbackScope) {
                    $validated['organizational_unit_id'] = $fallbackScope->organizational_unit_id;
                } else {
                    return response()->json(['message' => 'Aucun périmètre défini pour cet utilisateur.'], 422);
                }
            }
        }

        if ($user && !$user->isAdminOrSupervisor()) {
            $validated['statut'] = 'en_attente';
        } else {
            $validated['statut'] = 'valide';
        }

        // Mapper potentiel (string) → potential_id (UUID FK)
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

        // Validation dynamique du champ meta
        if (!empty($validated['account_type_id'])) {
            $type = AccountType::find($validated['account_type_id']);
            $validationService->validateMeta($validated['meta'] ?? null, $type);
        }

        // Détection de doublons (Bloque la requête si doublon)
        $duplicateService->abortIfDuplicateAccount($validated['organizational_unit_id'] ?? null, $validated);

        $user = auth()->user();
        $isDelegue = $user && !$user->isAdminOrSupervisor();
        $statutValidation = $isDelegue ? 'en_attente' : 'valide';

        // Mettre le nom en majuscules
        if (!empty($validated['nom'])) {
            $validated['nom'] = mb_strtoupper($validated['nom'], 'UTF-8');
        }

        $account = Account::create(array_merge(
            $validated,
            [
                'user_id' => $user->id ?? null,
                'statut_validation' => $statutValidation,
            ]
        ));

        if ($isDelegue) {
            $supervisors = \App\Models\User::role('superviseur')->where('team_id', $user->team_id)->get();
            if ($supervisors->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($supervisors, new \App\Notifications\PendingValidationNotification($account, $user));
            }
            $manager = $user?->manager ?? \App\Models\User::where('email', 'cmoucharif@gmail.com')->first() ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager']))->first();
            if ($manager) {
                NotificationController::sendNotification(
                    $manager,
                    'Nouvelle demande de validation établissement',
                    "Le délégué {$user->name} a créé l'établissement {$account->nom} en attente de validation.",
                    'validation',
                    'manager',
                    ['account_id' => (string) $account->id]
                );
            }
        }

        return new AccountResource($account->load(['organizationalUnit', 'categories']));
    }

    #[OA\Get(
        path: '/api/v1/accounts/{id}',
        summary: 'Afficher les détails d\'un compte',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du compte'),
            new OA\Response(response: 404, description: 'Compte non trouvé'),
        ]
    )]
    public function show(string $id)
    {
        $account = Account::with(['organizationalUnit', 'categories', 'contacts.delegue', 'contacts.speciality', 'activities', 'user', 'delegue'])->findOrFail($id);
        return new AccountResource($account);
    }

    #[OA\Put(
        path: '/api/v1/accounts/{id}',
        summary: 'Mettre à jour un compte',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'account_type', type: 'string', enum: ['pharmacie', 'hopital', 'clinique', 'grossiste', 'cabinet', 'entreprise', 'autre']),
                    new OA\Property(property: 'adresse', type: 'string', nullable: true),
                    new OA\Property(property: 'ville', type: 'string', nullable: true),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'statut', type: 'string', enum: ['actif', 'inactif', 'suspendu']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte mis à jour avec succès'),
            new OA\Response(response: 404, description: 'Compte non trouvé'),
        ]
    )]
    public function update(
        UpdateAccountRequest $request,
        string $id,
        DynamicValidationService $validationService,
        DuplicateDetectionService $duplicateService
    ) {
        $account = Account::findOrFail($id);
        
        \Log::info("Update payload for account {$id}:", $request->all());
        $validated = $request->validated();
        \Log::info("Validated payload:", $validated);

        // Validation dynamique
        $typeId = $validated['account_type_id'] ?? $account->account_type_id;
        if ($typeId) {
            $type = AccountType::find($typeId);
            // On fusionne les metas existants et les nouveaux pour valider
            $mergedMeta = array_merge($account->meta ?? [], $validated['meta'] ?? []);
            $validationService->validateMeta($mergedMeta, $type);
        }

        // Détection de doublons
        $subsidiaryId = $validated['organizational_unit_id'] ?? $account->organizational_unit_id ?? null;
        $dataForDuplicateCheck = array_merge($account->toArray(), $validated);
        $duplicateService->abortIfDuplicateAccount($subsidiaryId, $dataForDuplicateCheck, $account->id);

        // Mapper potentiel (string) → potential_id (UUID FK)
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

        $account->update($validated);
        return new AccountResource($account->load(['organizationalUnit', 'categories', 'user', 'delegue', 'accountType']));
    }

    #[OA\Delete(
        path: '/api/v1/accounts/{id}',
        summary: 'Supprimer (soft-delete) un compte',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Compte supprimé avec succès'),
            new OA\Response(response: 404, description: 'Compte non trouvé'),
        ]
    )]
    public function destroy(string $id)
    {
        Account::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    /**
     * Récupère tous les IDs descendants récursivement (Togo → Maritime → Lomé → ...)
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

    public function validateAccount(Request $request, string $id)
    {
        $account = Account::withoutGlobalScopes()->findOrFail($id);
        $user = auth()->user();

        if ($user && $user->hasRole('delegue_medical')) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $account->update([
            'statut_validation' => 'valide',
            'statut'            => 'actif',
        ]);

        return response()->json([
            'message' => 'Établissement validé avec succès.',
            'data'    => new AccountResource($account->load(['organizationalUnit', 'categories']))
        ]);
    }

    public function rejectAccount(Request $request, string $id)
    {
        $account = Account::withoutGlobalScopes()->findOrFail($id);
        $user = auth()->user();

        if ($user && $user->hasRole('delegue_medical')) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du rejet est obligatoire.'
        ]);

        $account->update([
            'statut_validation' => 'rejete',
            'statut'            => 'inactif',
            'rejection_reason'  => $validated['motif'],
        ]);

        return response()->json([
            'message' => 'Établissement rejeté avec enregistrement du motif.',
            'data'    => new AccountResource($account->load(['organizationalUnit', 'categories']))
        ]);
    }
}
