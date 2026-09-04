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
        $sales = Sale::with(['account', 'order', 'user'])->paginate(15);
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|uuid|exists:accounts,id',
            'order_id' => 'nullable|uuid|exists:orders,id',
            'invoice_number' => 'required|string|unique:sales',
            'amount' => 'required|numeric',
            'status' => 'required|in:unpaid,paid,cancelled',
            'sale_date' => 'required|date',
        ]);

        $data['user_id'] = Auth::id();
        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $sale = Sale::create($data);

        return response()->json($sale->load(['account', 'order']), 201);
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
