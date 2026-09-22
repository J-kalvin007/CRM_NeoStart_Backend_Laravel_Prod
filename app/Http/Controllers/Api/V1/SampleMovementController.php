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
        $user = $request->user();
        
        $movements = SampleMovement::with(['product', 'contact'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($movements);
    }

    public function myStock(Request $request)
    {
        $user = $request->user();
        
        $stocks = \App\Models\SampleStock::with(['product.therapeuticClass'])
            ->where('user_id', $user->id)
            ->get();

        if ($stocks->isEmpty()) {
            $products = \App\Models\Product::where('statut', 'actif')->get();
            foreach ($products as $idx => $p) {
                \App\Models\SampleStock::create([
                    'user_id' => $user->id,
                    'product_id' => $p->id,
                    'lot_number' => 'LOT-2026-' . strtoupper(substr(md5($p->code ?? $p->id), 0, 5)),
                    'expiry_date' => now()->addYears(2)->toDateString(),
                    'quantity' => 40 + ($idx * 5),
                ]);
            }
            $stocks = \App\Models\SampleStock::with(['product.therapeuticClass'])->where('user_id', $user->id)->get();
        }
            
        $formatted = $stocks->map(function ($stock) {
            $prodName = $stock->product->nom ?? 'Médicament';
            return [
                'id' => (string) $stock->id,
                'product_id' => (string) $stock->product_id,
                'product_name' => $prodName,
                'product' => [
                    'id' => (string) $stock->product_id,
                    'nom' => $prodName,
                    'name' => $prodName,
                    'gamme' => $stock->product->gamme ?? $stock->product->dci ?? 'Médicament',
                    'stock_alerte' => (int) ($stock->product->stock_alerte ?? 10),
                ],
                'lot_number' => $stock->lot_number,
                'expiry_date' => $stock->expiry_date ? \Carbon\Carbon::parse($stock->expiry_date)->format('m/Y') : null,
                'quantity_available' => (int) $stock->quantity,
                'quantity_distributed' => 12,
                'quantity_received' => (int) $stock->quantity + 12,
                'alert_threshold' => (int) ($stock->product->stock_alerte ?? 10),
                'category' => $stock->product->therapeuticClass->nom ?? 'Général',
            ];
        });

        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'product_id' => 'required|uuid',
            'movement_type' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'contact_id' => 'nullable|uuid',
            'remarques' => 'nullable|string'
        ]);

        $movementType = $validated['movement_type'];
        $lotNumber = null;
        $expiryDate = null;

        if (in_array($movementType, ['distribution', 'sortie_visite', 'out'])) {
            // Find a stock line with enough quantity
            $stock = \App\Models\SampleStock::where('user_id', $user->id)
                ->where('product_id', $validated['product_id'])
                ->where('quantity', '>=', $validated['quantity'])
                ->first();

            if (!$stock) {
                return response()->json(['message' => 'Stock insuffisant pour ce produit.'], 400);
            }

            $stock->decrement('quantity', $validated['quantity']);
            $lotNumber = $stock->lot_number;
            $expiryDate = $stock->expiry_date;
        } else {
            // Reception - Create a new lot if needed
            $lotNumber = 'LOT-' . date('Y-m') . '-' . strtoupper(substr(uniqid(), -4));
            $expiryDate = date('Y-m-d', strtotime('+1 year'));

            $stock = \App\Models\SampleStock::firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'lot_number' => $lotNumber
            ], [
                'expiry_date' => $expiryDate,
                'quantity' => 0
            ]);

            $stock->increment('quantity', $validated['quantity']);
        }

        $movement = SampleMovement::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'movement_type' => $validated['movement_type'],
            'quantity' => $validated['quantity'],
            'contact_id' => $validated['contact_id'] ?? null,
            'remarques' => $validated['remarques'] ?? null,
            'lot_number' => $lotNumber,
            'expiry_date' => $expiryDate,
            'date_mouvement' => $request->input('date_mouvement', date('Y-m-d'))
        ]);

        return response()->json($movement->load('product'), 201);
    }
}
