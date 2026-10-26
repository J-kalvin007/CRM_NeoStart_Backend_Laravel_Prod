<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/v1/products',
        summary: 'Lister tous les produits du catalogue',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['brouillon', 'actif', 'archive'])),
            new OA\Parameter(name: 'stock_alerte', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Filtrer les produits en alerte stock'),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Recherche par nom, code, marque'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des produits récupérée avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::with(['productType', 'laboratory', 'therapeuticClass', 'galenicForm', 'countries']);
        
        $user = auth()->user();
        $query->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class);

        $countryId = $request->input('country_id') ?? $request->header('X-Country-Id');

        // Restriction délégué : voit uniquement les produits qui lui sont affectés
        // Si aucun produit ne lui est affecté, il voit tous les produits de son pays
        if ($user && !$user->isAdminOrDirection()) {
            $assignedViaPivot = $user->products()->pluck('products.id')->toArray();
            $assignedViaStock = \App\Models\SampleStock::where('user_id', $user->id)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->toArray();
            $assignedViaMovements = \App\Models\SampleMovement::where('user_id', $user->id)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->toArray();
            $assignedViaUnit = [];
            if ($user->organizational_unit_id) {
                $assignedViaUnit = \Illuminate\Support\Facades\DB::table('organizational_unit_product')
                    ->where('organizational_unit_id', $user->organizational_unit_id)
                    ->pluck('product_id')
                    ->toArray();
            }

            $allAssignedIds = array_values(array_unique(array_merge($assignedViaPivot, $assignedViaStock, $assignedViaMovements, $assignedViaUnit)));

            if (!empty($allAssignedIds)) {
                $query->whereIn('products.id', $allAssignedIds);
            } else {
                // Aucun produit spécifiquement affecté au délégué : repli sur les produits de son pays
                $userCountryId = $user->country_id ?? $countryId;
                if ($userCountryId) {
                    $hasCountryRows = \Illuminate\Support\Facades\DB::table('country_product')->where('country_id', $userCountryId)->exists();
                    if ($hasCountryRows) {
                        $query->whereHas('countries', function ($subq) use ($userCountryId) {
                            $subq->where('countries.id', $userCountryId);
                        });
                    }
                }
            }
        } elseif ($countryId) {
            $hasCountryRows = \Illuminate\Support\Facades\DB::table('country_product')->where('country_id', $countryId)->exists();
            if ($hasCountryRows) {
                $query->whereHas('countries', function ($subq) use ($countryId) {
                    $subq->where('countries.id', $countryId);
                });
            }
        }

        $query = $query->when($request->product_type_id, fn($q, $v) => $q->where('product_type_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->boolean('stock_alerte'), fn($q) => $q->scopeStockAlerte())
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('code', 'like', "%$v%")
                  ->orWhere('marque', 'like', "%$v%");
            }));

        \App\Support\CountryFilter::apply($query);
        return ProductResource::collection($query->paginate(min(1000, max(1, $request->integer('per_page', 25)))));
    }

    #[OA\Post(
        path: '/api/v1/products',
        summary: 'Créer un nouveau produit dans le catalogue',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'product_type_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'nom', type: 'string', example: 'Amoxicilline 500mg'),
                    new OA\Property(property: 'code', type: 'string', example: 'AMX-500', nullable: true),
                    new OA\Property(property: 'marque', type: 'string', nullable: true),
                    new OA\Property(property: 'gamme', type: 'string', nullable: true),
                    new OA\Property(property: 'laboratory_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'therapeutic_class_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'galenic_form_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'pght', type: 'number', nullable: true),
                    new OA\Property(property: 'prix_cession', type: 'number', nullable: true),
                    new OA\Property(property: 'ppv', type: 'number', nullable: true),
                    new OA\Property(property: 'devise', type: 'string', nullable: true),
                    new OA\Property(property: 'dci', type: 'string', nullable: true),
                    new OA\Property(property: 'conditionnement', type: 'string', nullable: true),
                    new OA\Property(property: 'indications', type: 'string', nullable: true),
                    new OA\Property(property: 'posologie', type: 'string', nullable: true),
                    new OA\Property(property: 'effets_secondaires', type: 'string', nullable: true),
                    new OA\Property(property: 'stock_actuel', type: 'integer', default: 0),
                    new OA\Property(property: 'stock_alerte', type: 'integer', default: 0),
                    new OA\Property(property: 'statut', type: 'string', enum: ['brouillon', 'actif', 'archive']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Produit créé avec succès'),
            new OA\Response(response: 422, description: 'Données de validation invalides'),
        ]
    )]
    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        
        // Remove 'countries' from $validated to prevent mass assignment issues
        $countriesData = $validated['countries'] ?? [];
        unset($validated['countries']);

        $product = Product::create($validated);

        if (!empty($countriesData)) {
            $syncData = [];
            foreach ($countriesData as $c) {
                $syncData[$c['country_id']] = [
                    'pght' => $c['pght'] ?? null,
                    'prix_cession' => $c['prix_cession'] ?? null,
                    'ppv' => $c['ppv'] ?? null,
                    'devise' => $c['devise'] ?? null,
                    'stock_actuel' => $c['stock_actuel'] ?? 0,
                    'stock_alerte' => $c['stock_alerte'] ?? 0,
                ];
            }
            $product->countries()->sync($syncData);
        }

        return new ProductResource($product->load(['productType', 'laboratory', 'therapeuticClass', 'galenicForm', 'countries']));
    }

    #[OA\Get(
        path: '/api/v1/products/{id}',
        summary: 'Afficher les détails d\'un produit',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du produit'),
            new OA\Response(response: 404, description: 'Produit non trouvé'),
        ]
    )]
    public function show(string $id)
    {
        return new ProductResource(Product::with(['productType', 'subsidiary', 'laboratory', 'therapeuticClass', 'galenicForm', 'countries'])->findOrFail($id));
    }

    #[OA\Put(
        path: '/api/v1/products/{id}',
        summary: 'Mettre à jour un produit',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'laboratory_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'therapeutic_class_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'galenic_form_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'dci', type: 'string', nullable: true),
                    new OA\Property(property: 'conditionnement', type: 'string', nullable: true),
                    new OA\Property(property: 'indications', type: 'string', nullable: true),
                    new OA\Property(property: 'posologie', type: 'string', nullable: true),
                    new OA\Property(property: 'effets_secondaires', type: 'string', nullable: true),
                    new OA\Property(property: 'stock_alerte', type: 'integer'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['brouillon', 'actif', 'archive']),

                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Produit mis à jour avec succès'),
            new OA\Response(response: 404, description: 'Produit non trouvé'),
        ]
    )]
    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);
        
        $validated = $request->validated();
        
        $countriesData = $validated['countries'] ?? [];
        unset($validated['countries']);

        // Extract stock fields before calling update() - they are NOT in Product $fillable
        // (they live on the pivot table, not the products table)
        $stockActuel = $validated['stock_actuel'] ?? null;
        $stockAlerte = $validated['stock_alerte'] ?? null;
        unset($validated['stock_actuel'], $validated['stock_alerte']);

        // Update only fillable Product fields
        $product->update($validated);
        
        // Handle direct stock updates from central-stock page
        if ($stockActuel !== null || $stockAlerte !== null) {
            // Determine the country to update: user's country, or fallback to the product's first country
            $userCountryId = $request->user()->country_id;
            
            if (!$userCountryId) {
                // Super admin may not have a country_id, use the product's first country
                $firstCountry = $product->countries()->first();
                $userCountryId = $firstCountry?->id;
            }
            
            if ($userCountryId) {
                $pivotData = [];
                if ($stockActuel !== null) $pivotData['stock_actuel'] = $stockActuel;
                if ($stockAlerte !== null) $pivotData['stock_alerte'] = $stockAlerte;
                
                // Check if the pivot exists, if not attach the country first
                if ($product->countries()->where('countries.id', $userCountryId)->exists()) {
                    $product->countries()->updateExistingPivot($userCountryId, $pivotData);
                } else {
                    $product->countries()->attach($userCountryId, $pivotData);
                }
            }
        }

        if ($request->has('countries')) { // Only sync if countries key is present in request
            $syncData = [];
            foreach ($countriesData as $c) {
                $syncData[$c['country_id']] = [
                    'pght' => $c['pght'] ?? null,
                    'prix_cession' => $c['prix_cession'] ?? null,
                    'ppv' => $c['ppv'] ?? null,
                    'devise' => $c['devise'] ?? null,
                    'stock_actuel' => $c['stock_actuel'] ?? 0,
                    'stock_alerte' => $c['stock_alerte'] ?? 0,
                ];
            }
            $product->countries()->sync($syncData);
        }
        
        return new ProductResource($product->load(['productType', 'laboratory', 'therapeuticClass', 'galenicForm', 'countries']));
    }

    #[OA\Delete(
        path: '/api/v1/products/{id}',
        summary: 'Archiver (soft-delete) un produit',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Produit supprimé avec succès'),
            new OA\Response(response: 404, description: 'Produit non trouvé'),
        ]
    )]
    public function destroy(string $id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function syncUserProducts(Request $request, \App\Models\User $user)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);
        $user->products()->sync($request->input('product_ids'));
        return response()->json([
            'message' => 'Produits affectés avec succès au délégué.',
            'product_ids' => $user->products()->pluck('products.id'),
        ]);
    }
}
