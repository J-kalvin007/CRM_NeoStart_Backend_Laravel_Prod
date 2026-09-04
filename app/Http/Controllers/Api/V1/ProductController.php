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
        $query = Product::with('productType')
            ->when($request->product_type_id, fn($q, $v) => $q->where('product_type_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->boolean('stock_alerte'), fn($q) => $q->scopeStockAlerte())
            ->when($request->search, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%$v%")
                  ->orWhere('code', 'like', "%$v%")
                  ->orWhere('marque', 'like', "%$v%");
            }));

        return ProductResource::collection($query->paginate(25));
    }

    #[OA\Post(
        path: '/api/v1/products',
        summary: 'Créer un nouveau produit dans le catalogue',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subsidiary_id', 'nom'],
                properties: [
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'product_type_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'nom', type: 'string', example: 'Amoxicilline 500mg'),
                    new OA\Property(property: 'code', type: 'string', example: 'AMX-500', nullable: true),
                    new OA\Property(property: 'marque', type: 'string', nullable: true),
                    new OA\Property(property: 'gamme', type: 'string', nullable: true),
                    new OA\Property(property: 'prix_unitaire', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'stock_actuel', type: 'integer', default: 0),
                    new OA\Property(property: 'stock_alerte', type: 'integer', default: 0),
                    new OA\Property(property: 'statut', type: 'string', enum: ['brouillon', 'actif', 'archive']),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        nullable: true,
                        description: 'Attributs sectoriels libres. Pharma ex: {"molecule":"Amoxicilline","dosage":"500mg","forme":"Comprimé","numero_amm":"AMM-001"}',
                        example: ['molecule' => 'Amoxicilline', 'dosage' => '500mg', 'forme' => 'Comprimé']
                    ),
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
        $product = Product::create($request->validated());
        return new ProductResource($product->load('productType'));
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
        return new ProductResource(Product::with(['productType', 'subsidiary'])->findOrFail($id));
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
                    new OA\Property(property: 'prix_unitaire', type: 'number', format: 'float'),
                    new OA\Property(property: 'stock_alerte', type: 'integer'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['brouillon', 'actif', 'archive']),
                    new OA\Property(property: 'meta', type: 'object', nullable: true),
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
        $product->update($request->validated());
        return new ProductResource($product->load('productType'));
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
}
