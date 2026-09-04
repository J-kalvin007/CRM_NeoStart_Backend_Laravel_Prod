<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SampleMovementResource;
use App\Http\Requests\StoreSampleMovementRequest;
use App\Models\SampleMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SampleMovementController extends Controller
{
    #[OA\Get(path: '/api/v1/sample-movements', summary: 'Lister les mouvements de stock d\'échantillons', security: [['bearerAuth' => []]], tags: ['SampleMovements'],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'movement_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['reception','attribution','distribution','retour','destruction','inventaire'])),
            new OA\Parameter(name: 'lot_number', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des mouvements de stock')]
    )]
    public function index(Request $request)
    {
        $query = SampleMovement::with(['product', 'user', 'activity'])
            ->when($request->product_id, fn($q, $v) => $q->where('product_id', $v))
            ->when($request->movement_type, fn($q, $v) => $q->where('movement_type', $v))
            ->when($request->lot_number, fn($q, $v) => $q->where('lot_number', 'like', "%$v%"))
            ->latest();
        return SampleMovementResource::collection($query->paginate(30));
    }

    #[OA\Post(path: '/api/v1/sample-movements', summary: 'Enregistrer un mouvement de stock',
        description: 'Crée un mouvement de stock (réception, distribution, retour...) et met à jour automatiquement le stock du produit.',
        security: [['bearerAuth' => []]], tags: ['SampleMovements'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['product_id', 'movement_type', 'quantity'],
            properties: [
                new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'activity_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'movement_type', type: 'string', enum: ['reception','attribution','distribution','retour','destruction','inventaire']),
                new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
                new OA\Property(property: 'lot_number', type: 'string', nullable: true),
                new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'signature_url', type: 'string', nullable: true),
                new OA\Property(property: 'qr_code', type: 'string', nullable: true),
                new OA\Property(property: 'remarques', type: 'string', nullable: true),
                new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Mouvement enregistré avec succès'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(StoreSampleMovementRequest $request)
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);

        // Mise à jour du stock selon le type de mouvement
        $delta = match($data['movement_type']) {
            'reception', 'retour'             =>  $data['quantity'],
            'distribution', 'destruction'     => -$data['quantity'],
            'attribution'                     =>  0, // mouvement interne (pas de delta global)
            'inventaire'                      =>  0, // correction manuelle via stock_actuel
            default                           =>  0,
        };

        $newStock = max(0, $product->stock_actuel + $delta);
        $data['stock_apres'] = $newStock;
        $data['user_id'] = auth()->id();

        $movement = SampleMovement::create($data);
        $product->update(['stock_actuel' => $newStock]);

        return new SampleMovementResource($movement->load(['product', 'user', 'activity']));
    }

    #[OA\Get(path: '/api/v1/sample-movements/{id}', summary: 'Détails d\'un mouvement de stock', security: [['bearerAuth' => []]], tags: ['SampleMovements'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Détails du mouvement')]
    )]
    public function show(string $id)
    {
        return new SampleMovementResource(SampleMovement::with(['product', 'user', 'activity'])->findOrFail($id));
    }

    public function myStock(Request $request)
    {
        $userId = $request->user()->id;

        // Aggregate stock per product for the authenticated delegate
        $stock = \Illuminate\Support\Facades\DB::table('sample_movements')
            ->where('user_id', $userId)
            ->join('products', 'sample_movements.product_id', '=', 'products.id')
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.image_url',
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN movement_type IN ('reception','retour') THEN quantity ELSE 0 END) as quantity_received"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN movement_type IN ('distribution','destruction') THEN quantity ELSE 0 END) as quantity_distributed"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN movement_type IN ('reception','retour') THEN quantity ELSE -quantity END) as quantity_available")
            )
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->get()
            ->map(fn($row) => [
                'product_id'           => $row->product_id,
                'product_name'         => $row->product_name,
                'image_url'            => $row->image_url,
                'quantity_available'   => max(0, $row->quantity_available),
                'quantity_distributed' => $row->quantity_distributed,
                'quantity_received'    => $row->quantity_received,
                'alert_threshold'      => 5, // configurable
            ]);

        return response()->json(['data' => $stock]);
    }
}
