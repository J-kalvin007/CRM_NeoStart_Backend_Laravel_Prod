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
        $orders = Order::with(['account', 'opportunity', 'user', 'items'])->get()->map(function ($order) {
            $itemsCount = $order->items ? $order->items->sum('quantity') : 0;
            return [
                'id' => $order->id,
                'reference' => $order->order_number,
                'clientNom' => $order->account ? $order->account->nom : 'Client Officine',
                'clientType' => $order->account ? ($order->account->type ?? 'pharmacie') : 'pharmacie',
                'produitsCount' => $itemsCount > 0 ? (int) $itemsCount : 1,
                'montantTotal' => (float) $order->total_amount,
                'devise' => 'FCFA',
                'statut' => $order->status, // draft, pending, confirmed, cancelled, invoiced
                'dateCommande' => $order->order_date,
                'grossisteRelais' => $order->grossiste_relais ?? 'Grossiste Répartiteur',
                'remiseAppliquee' => (float) ($order->discount_rate ?? 0)
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
        // Frontend sends: clientNom, clientType, grossisteRelais, produitsCount, montantTotal, devise, remiseAppliquee
        // We map it to backend structure. We find or create an account for simplicity.
        
        $account = \App\Models\Account::firstOrCreate(
            ['nom' => $request->clientNom ?? 'Client Officine'],
            ['subsidiary_id' => Auth::user()->subsidiary_id ?? null, 'user_id' => Auth::id()] // Type must be resolved via meta or another way, keep it simple
        );

        $order = Order::create([
            'account_id' => $account->id,
            'user_id' => Auth::id(),
            'subsidiary_id' => Auth::user()->subsidiary_id ?? null,
            'order_number' => 'CMD-2026-' . rand(1000, 9999),
            'total_amount' => $request->montantTotal ?? 150000,
            'status' => 'pending',
            'order_date' => now()->toDateString()
        ]);

        return response()->json([
            'id' => $order->id,
            'reference' => $order->order_number,
            'clientNom' => $account->nom,
            'clientType' => 'pharmacie', // default or use meta
            'produitsCount' => $request->produitsCount ?? 10,
            'montantTotal' => $order->total_amount,
            'devise' => 'FCFA',
            'statut' => 'soumise',
            'dateCommande' => $order->order_date,
            'grossisteRelais' => $request->grossisteRelais ?? 'CAMEG Togo',
            'remiseAppliquee' => $request->remiseAppliquee ?? 5
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
