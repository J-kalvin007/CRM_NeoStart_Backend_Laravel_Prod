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
        $orders = Order::with(['account', 'opportunity', 'user'])->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'reference' => $order->order_number,
                'clientNom' => $order->account?->nom,
                'clientType' => $order->account?->type,
                'produitsCount' => null,
                'montantTotal' => (float) $order->total_amount,
                'devise' => 'FCFA',
                'statut' => $order->status, // draft, pending, confirmed, cancelled, invoiced
                'dateCommande' => $order->order_date,
                'grossisteRelais' => $order->grossiste_relais,
                'remiseAppliquee' => $order->discount_rate === null ? null : (float) $order->discount_rate
            ];
        });

        // Remap status for frontend expectations
        $mappedOrders = $orders->map(function($order) {
            $mappedStatus = 'soumise';
            if ($order['statut'] === 'confirmed') $mappedStatus = 'validee';
            if ($order['statut'] === 'invoiced') $mappedStatus = 'livree';
            if ($order['statut'] === 'cancelled') $mappedStatus = 'annulee';
            $order['statut'] = $mappedStatus;
            return $order;
        });

        return response()->json($mappedOrders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'clientNom' => 'required|string|max:255',
            'montantTotal' => 'required|numeric|min:0',
            'clientType' => 'nullable|in:pharmacie,hopital,grossiste',
        ]);
        $account = \App\Models\Account::firstOrCreate(['nom' => $data['clientNom']], ['user_id' => Auth::id(), 'country_id' => \App\Support\CountryFilter::resolveCountryId() ?? Auth::user()->country_id]);
        $order = Order::create([
            'account_id' => $account->id, 'user_id' => Auth::id(),
            'order_number' => 'CMD-'.now()->year.'-'.\Illuminate\Support\Str::ulid(),
            'total_amount' => $data['montantTotal'], 'status' => 'pending', 'order_date' => now()->toDateString(),
        ]);
        return response()->json([
            'id' => $order->id, 'reference' => $order->order_number, 'clientNom' => $account->nom,
            'clientType' => $account->type, 'produitsCount' => null, 'montantTotal' => (float) $order->total_amount,
            'devise' => 'FCFA', 'statut' => 'soumise', 'dateCommande' => $order->order_date,
            'grossisteRelais' => null, 'remiseAppliquee' => null,
        ], 201);
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
