<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Sale::with(['account', 'order', 'user']);
        if ($user && !$user->isAdminOrSupervisor()) {
            $query->where('user_id', $user->id);
        }
        $sales = $query->paginate(15);
        return response()->json($sales);
    }

    #[OA\Post(
        path: '/api/v1/sales',
        summary: 'Enregistrer un relevé de ventes grossiste',
        security: [['bearerAuth' => []]],
        tags: ['Sales'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account_id', 'date_releve', 'mois_concerne', 'products'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'date_releve', type: 'string', format: 'date'),
                    new OA\Property(property: 'mois_concerne', type: 'string', example: '2026-08'),
                    new OA\Property(property: 'products', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'quantite_vendue', type: 'integer'),
                            new OA\Property(property: 'ca_genere', type: 'number'),
                        ]
                    )),
                    new OA\Property(property: 'commentaires', type: 'string'),
                    new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Relevé enregistré avec succès'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|string|exists:accounts,id',
            'date_releve' => 'required|date',
            'mois_concerne' => 'required|string',
            'products' => 'required|array',
            'commentaires' => 'nullable|string',
            'local_uuid' => 'nullable|string',
        ]);

        // Calculer le montant total
        $totalAmount = 0;
        foreach ($data['products'] as $product) {
            $totalAmount += $product['ca_genere'] ?? 0;
        }

        $sale = Sale::create([
            'user_id' => Auth::id(),
            'subsidiary_id' => Auth::user()->subsidiary_id ?? null,
            'account_id' => $data['account_id'],
            'sale_date' => $data['date_releve'],
            'mois_concerne' => $data['mois_concerne'],
            'products_json' => $data['products'],
            'commentaires' => $data['commentaires'] ?? null,
            'local_uuid' => $data['local_uuid'] ?? null,
            'amount' => $totalAmount,
            'status' => 'paid', // Default for wholesale reporting
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Relevé de ventes grossiste enregistré et transmis.',
            'data' => $sale->load(['account', 'order']),
        ], 201);
    }

    public function show(Sale $sale)
    {
        return response()->json($sale->load(['account', 'order', 'user']));
    }

    public function update(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'status' => 'sometimes|required|in:unpaid,paid,cancelled',
            'amount' => 'sometimes|numeric',
        ]);

        $sale->update($data);
        return response()->json($sale->load(['account', 'order']));
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();
        return response()->json(null, 204);
    }
}
