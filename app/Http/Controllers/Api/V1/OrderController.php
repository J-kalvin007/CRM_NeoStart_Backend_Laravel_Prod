<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['account', 'opportunity', 'user'])->paginate(15);
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|uuid|exists:accounts,id',
            'opportunity_id' => 'nullable|uuid|exists:opportunities,id',
            'order_number' => 'required|string|unique:orders',
            'total_amount' => 'required|numeric',
            'status' => 'required|in:draft,pending,confirmed,cancelled,invoiced',
            'order_date' => 'required|date',
        ]);

        $data['user_id'] = Auth::id();
        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $order = Order::create($data);

        return response()->json($order->load(['account', 'opportunity']), 201);
    }

    public function show(Order $order)
    {
        return response()->json($order->load(['account', 'opportunity', 'user']));
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'sometimes|required|in:draft,pending,confirmed,cancelled,invoiced',
            'total_amount' => 'sometimes|numeric',
        ]);

        $order->update($data);
        return response()->json($order->load(['account', 'opportunity']));
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(null, 204);
    }
}
