<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\AccountType;
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
        $query = Account::with(['subsidiary', 'categories'])
            ->when($request->account_type, fn($q, $v) => $q->where('account_type', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('ville', 'like', "%$v%")
                  ->orWhere('responsable', 'like', "%$v%");
            }));

        return AccountResource::collection($query->paginate(25));
    }

    #[OA\Post(
        path: '/api/v1/accounts/check-duplicates',
        summary: 'Vérifier l\'existence de doublons potentiels',
        security: [['bearerAuth' => []]],
        tags: ['Accounts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subsidiary_id'],
                properties: [
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
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
            'subsidiary_id' => 'required|uuid|exists:subsidiaries,id',
            'nom' => 'nullable|string',
            'ville' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $duplicate = $duplicateService->findDuplicateAccount($request->subsidiary_id, $request->all());

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
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
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

        // Validation dynamique du champ meta
        if (!empty($validated['account_type_id'])) {
            $type = AccountType::find($validated['account_type_id']);
            $validationService->validateMeta($validated['meta'] ?? null, $type);
        }

        // Détection de doublons (Bloque la requête si doublon)
        $duplicateService->abortIfDuplicateAccount($validated['subsidiary_id'], $validated);

        $account = Account::create(array_merge(
            $validated,
            ['user_id' => auth()->id()]
        ));

        return new AccountResource($account->load(['subsidiary', 'categories']));
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
        $account = Account::with(['subsidiary', 'categories', 'contacts', 'activities'])->findOrFail($id);
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
        $validated = $request->validated();

        // Validation dynamique
        $typeId = $validated['account_type_id'] ?? $account->account_type_id;
        if ($typeId) {
            $type = AccountType::find($typeId);
            // On fusionne les metas existants et les nouveaux pour valider
            $mergedMeta = array_merge($account->meta ?? [], $validated['meta'] ?? []);
            $validationService->validateMeta($mergedMeta, $type);
        }

        // Détection de doublons (en ignorant l'ID actuel)
        $subsidiaryId = $validated['subsidiary_id'] ?? $account->subsidiary_id;
        $dataForDuplicateCheck = array_merge($account->toArray(), $validated);
        $duplicateService->abortIfDuplicateAccount($subsidiaryId, $dataForDuplicateCheck, $account->id);

        $account->update($validated);
        return new AccountResource($account->load(['subsidiary', 'categories']));
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
}
