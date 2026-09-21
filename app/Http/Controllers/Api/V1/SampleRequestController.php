<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleRequest;
use App\Models\SampleStock;
use App\Models\SampleMovement;
use Illuminate\Http\Request;

class SampleRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = SampleRequest::with(['product', 'user', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($user->hasRole('délégué')) {
            $query->where('user_id', $user->id);
        } 

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        $sampleRequest = SampleRequest::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null
        ]);

        return response()->json($sampleRequest->load(['product', 'user']), 201);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->hasRole('superviseur') && !$user->hasRole('directeur_marketing') && !$user->hasRole('superadmin')) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $sampleRequest = SampleRequest::findOrFail($id);

        if ($sampleRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande est déjà traitée.'], 400);
        }

        $sampleRequest->status = 'approved';
        $sampleRequest->approved_by = $user->id;
        $sampleRequest->save();

        $lotNumber = 'LOT-' . date('Y-m') . '-' . strtoupper(substr(uniqid(), -4));
        $expiryDate = date('Y-m-d', strtotime('+1 year'));

        $stock = SampleStock::firstOrCreate([
            'user_id' => $sampleRequest->user_id,
            'product_id' => $sampleRequest->product_id,
            'lot_number' => $lotNumber
        ], [
            'expiry_date' => $expiryDate,
            'quantity' => 0
        ]);

        $stock->increment('quantity', $sampleRequest->quantity);

        SampleMovement::create([
            'user_id' => $sampleRequest->user_id,
            'product_id' => $sampleRequest->product_id,
            'movement_type' => 'reception',
            'quantity' => $sampleRequest->quantity,
            'remarques' => 'Approuvé par ' . $user->name,
            'lot_number' => $lotNumber,
            'expiry_date' => $expiryDate,
            'date_mouvement' => date('Y-m-d')
        ]);

        return response()->json($sampleRequest->load(['product', 'user', 'approver']));
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->hasRole('superviseur') && !$user->hasRole('directeur_marketing') && !$user->hasRole('superadmin')) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $sampleRequest = SampleRequest::findOrFail($id);

        if ($sampleRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande est déjà traitée.'], 400);
        }

        $sampleRequest->status = 'rejected';
        $sampleRequest->approved_by = $user->id;
        $sampleRequest->save();

        return response()->json($sampleRequest->load(['product', 'user', 'approver']));
    }
}
