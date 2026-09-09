<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleMovement;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SampleMovementController extends Controller
{
    #[OA\Get(
        path: '/api/v1/sample-movements',
        summary: 'Lister les stocks d\'échantillons du délégué',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des stocks avec quantités disponibles et distribuées',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'product', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                            ]),
                            new OA\Property(property: 'quantity_available', type: 'integer'),
                            new OA\Property(property: 'quantity_distributed', type: 'integer'),
                            new OA\Property(property: 'alert_threshold', type: 'integer')
                        ]
                    )
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        // For simplicity, we'll mock the response based on products or fetch actual movements
        // In a real scenario, this would aggregate `sample_movements` table data
        
        $user = $request->user();
        
        $movements = SampleMovement::with('product')
            ->where('user_id', $user->id)
            ->get();
            
        // Group by product
        $stock = $movements->groupBy('product_id')->map(function ($items, $productId) {
            $in = $items->where('type', 'in')->sum('quantity');
            $out = $items->where('type', 'out')->sum('quantity');
            
            return [
                'product' => [
                    'id' => $productId,
                    'name' => $items->first()->product->name ?? 'Produit inconnu',
                ],
                'quantity_available' => max(0, $in - $out),
                'quantity_distributed' => $out,
                'alert_threshold' => 10,
            ];
        })->values();

        return response()->json($stock);
    }
}
