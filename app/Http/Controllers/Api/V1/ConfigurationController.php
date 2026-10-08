<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AccountTypeResource;
use App\Http\Resources\ContactTypeResource;
use App\Http\Resources\ProductTypeResource;
use App\Models\AccountType;
use App\Models\ContactType;
use App\Models\ProductType;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Http\Requests\UpdateAccountTypeRequest;
use App\Http\Requests\StoreContactTypeRequest;
use App\Http\Requests\UpdateContactTypeRequest;
use App\Http\Requests\StoreProductTypeRequest;
use App\Http\Requests\UpdateProductTypeRequest;
use OpenApi\Attributes as OA;

/**
 * Gestion des tables de configuration (Types configurables par l'Administrateur).
 * Ces endpoints permettent de personnaliser le vocabulaire métier sans toucher au code.
 */
class ConfigurationController extends Controller
{
 
    #[OA\Get(
        path: '/api/v1/config/account-types',
        summary: 'Lister les types de comptes configurables',
        description: 'Retourne la liste des types de comptes définis par l\'administrateur (ex: Pharmacie, Hôpital, Grossiste pour le secteur Pharma).',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des types de comptes'),
        ]
    )]
    public function accountTypeIndex(Request $request)
    {
        $user = auth()->user();
        $query = AccountType::with(['activityTypes', 'countries']);
        $countryId = $request->input('country_id') ?? $request->header('X-Country-Id');
        
        // Pour un délégué qui n'a pas accès au filtre par pays, on force son propre pays.
        if (!$countryId && $user && !$user->isAdminOrSupervisor()) {
            $countryId = $user->country_id;
        }
        
        if ($countryId) {
            $query->where(function ($q) use ($countryId) {
                $q->whereHas('countries', function ($subQ) use ($countryId) {
                    $subQ->where('countries.id', $countryId);
                })->orWhereDoesntHave('countries');
            });
        }

        if ($user && $user->isAdminOrSupervisor()) {
            return AccountTypeResource::collection($query->get());
        }
        return AccountTypeResource::collection($query->active()->get());
    }

    public function configurations(Request $request)
    {
        $user = auth()->user();
        $query = AccountType::with(['activityTypes', 'countries']);
        $countryId = $request->input('country_id') ?? $request->header('X-Country-Id');

        if ($countryId) {
            $query->whereHas('countries', function ($q) use ($countryId) {
                $q->where('countries.id', $countryId);
            });
        }

        $types = ($user && $user->isAdminOrSupervisor())
            ? $query->get()
            : $query->active()->get();

        return response()->json([
            'accountTypes' => AccountTypeResource::collection($types),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/config/account-types',
        summary: 'Créer un nouveau type de compte',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'libelle'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'pharmacie', description: 'Identifiant technique unique'),
                    new OA\Property(property: 'libelle', type: 'string', example: 'Pharmacie'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'couleur', type: 'string', example: '#3B82F6'),
                    new OA\Property(property: 'icone', type: 'string', example: 'pharmacy-icon'),
                    new OA\Property(property: 'ordre', type: 'integer', default: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Type de compte créé avec succès'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function accountTypeStore(StoreAccountTypeRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if ($user && !$user->isAdminOrSupervisor()) {
            $validated['statut'] = 'en_attente';
        } else {
            $validated['statut'] = 'valide';
        }
        $type = AccountType::create($validated);

        if ($request->has('activity_type_ids') && is_array($request->input('activity_type_ids'))) {
            $type->activityTypes()->sync($request->input('activity_type_ids'));
        }

        if ($request->has('country_ids') && is_array($request->input('country_ids'))) {
            $type->countries()->sync($request->input('country_ids'));
        }

        return new AccountTypeResource($type->load(['activityTypes', 'countries']));
    }

    #[OA\Put(
        path: '/api/v1/config/account-types/{id}',
        summary: 'Mettre à jour un type de compte',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Type mis à jour'), new OA\Response(response: 404, description: 'Non trouvé')]
    )]
    public function accountTypeUpdate(UpdateAccountTypeRequest $request, string $id)
    {
        $type = AccountType::findOrFail($id);
        $type->update($request->validated());

        if ($request->has('activity_type_ids') && is_array($request->input('activity_type_ids'))) {
            $type->activityTypes()->sync($request->input('activity_type_ids'));
        }

        if ($request->has('country_ids') && is_array($request->input('country_ids'))) {
            $type->countries()->sync($request->input('country_ids'));
        }

        return new AccountTypeResource($type->load(['activityTypes', 'countries']));
    }

    #[OA\Delete(
        path: '/api/v1/config/account-types/{id}',
        summary: 'Supprimer un type de compte',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function accountTypeDestroy(string $id)
    {
        AccountType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════════════════════
    // CONTACT TYPES
    // ═══════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/config/contact-types',
        summary: 'Lister les types de contacts configurables',
        description: 'Retourne la liste des types de contacts (ex: Cardiologue, Pédiatre, Généraliste pour le secteur Pharma).',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        responses: [new OA\Response(response: 200, description: 'Liste des types de contacts')]
    )]
    public function contactTypeIndex()
    {
        $user = auth()->user();
        if ($user && $user->isAdminOrSupervisor()) {
            return ContactTypeResource::collection(ContactType::all());
        }
        return ContactTypeResource::collection(ContactType::active()->get());
    }

    #[OA\Post(
        path: '/api/v1/config/contact-types',
        summary: 'Créer un nouveau type de contact',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'libelle'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'cardiologue'),
                new OA\Property(property: 'libelle', type: 'string', example: 'Cardiologue'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'couleur', type: 'string', example: '#EF4444'),
                new OA\Property(property: 'ordre', type: 'integer'),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Type de contact créé')]
    )]
    public function contactTypeStore(StoreContactTypeRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if ($user && !$user->isAdminOrSupervisor()) {
            $validated['statut'] = 'en_attente';
        } else {
            $validated['statut'] = 'valide';
        }
        return new ContactTypeResource(ContactType::create($validated));
    }

    #[OA\Put(
        path: '/api/v1/config/contact-types/{id}',
        summary: 'Mettre à jour un type de contact',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Type mis à jour')]
    )]
    public function contactTypeUpdate(UpdateContactTypeRequest $request, string $id)
    {
        $type = ContactType::findOrFail($id);
        $type->update($request->validated());
        return new ContactTypeResource($type);
    }

    #[OA\Delete(
        path: '/api/v1/config/contact-types/{id}',
        summary: 'Supprimer un type de contact',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function contactTypeDestroy(string $id)
    {
        ContactType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════════════════════
    // PRODUCT TYPES
    // ═══════════════════════════════════════════════════════════

    #[OA\Get(
        path: '/api/v1/config/product-types',
        summary: 'Lister les types de produits configurables',
        description: 'Retourne les types de produits (ex: Article Vente Rx, Échantillon Gratuit, Brochure pour Pharma). Les champs is_stockable et is_distributable pilotent le comportement du système.',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        responses: [new OA\Response(response: 200, description: 'Liste des types de produits')]
    )]
    public function productTypeIndex()
    {
        return ProductTypeResource::collection(ProductType::active()->get());
    }

    #[OA\Post(
        path: '/api/v1/config/product-types',
        summary: 'Créer un nouveau type de produit',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['code', 'libelle'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'echantillon'),
                new OA\Property(property: 'libelle', type: 'string', example: 'Échantillon Gratuit'),
                new OA\Property(property: 'is_stockable', type: 'boolean', default: true),
                new OA\Property(property: 'is_distributable', type: 'boolean', default: false),
                new OA\Property(property: 'couleur', type: 'string', example: '#10B981'),
                new OA\Property(property: 'ordre', type: 'integer'),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Type de produit créé')]
    )]
    public function productTypeStore(StoreProductTypeRequest $request)
    {
        return new ProductTypeResource(ProductType::create($request->validated()));
    }

    #[OA\Put(
        path: '/api/v1/config/product-types/{id}',
        summary: 'Mettre à jour un type de produit',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Type mis à jour')]
    )]
    public function productTypeUpdate(UpdateProductTypeRequest $request, string $id)
    {
        $type = ProductType::findOrFail($id);
        $type->update($request->validated());
        return new ProductTypeResource($type);
    }

    #[OA\Delete(
        path: '/api/v1/config/product-types/{id}',
        summary: 'Supprimer un type de produit',
        security: [['bearerAuth' => []]],
        tags: ['Configuration'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function productTypeDestroy(string $id)
    {
        ProductType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
