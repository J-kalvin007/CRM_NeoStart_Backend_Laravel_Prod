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

        // Délégué : ses demandes ; hiérarchie : les siennes + celles de son équipe ; admin : tout
        if (!($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']))) {
            $query->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds()));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $productId = $request->input('product_id');
        $product = \App\Models\Product::where('id', $productId)->first();
        if (!$product) {
            $product = \App\Models\Product::first();
        }

        $quantity = (int) ($request->input('quantity') ?? $request->input('quantity_requested') ?? 1);
        $notes = $request->input('notes') ?? $request->input('motif') ?? 'Demande de réassort d\'échantillons';

        $sampleRequest = SampleRequest::create([
            'user_id' => $user->id,
            'product_id' => $product?->id,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'status' => 'pending',
            'notes' => $notes,
        ]);

        // Envoyer une notification au Manager / Superviseur
        // La demande est adressée au supérieur direct du délégué
        $manager = $user?->manager;
        if ($manager) {
            NotificationController::sendNotification(
                $manager,
                'Nouvelle Demande de Stock',
                "Le délégué {$user->name} a soumis une demande de réassort de " . ($product ? ($product->nom ?: $product->name) : 'échantillons') . " ({$quantity} boîtes).",
                'sample',
                'superviseur',
                ['request_id' => (string) $sampleRequest->id]
            );
        }

        return response()->json($sampleRequest->load(['product', 'user']), 201);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isAdminOrSupervisor() && !$user->hasRole(['superviseur', 'directeur_marketing', 'superadmin', 'manager', 'Manager'])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $sampleRequest = SampleRequest::findOrFail($id);
        if (!(($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) || $user->manages($sampleRequest->user_id))) {
            return response()->json(['message' => "Cette demande n'appartient pas à un délégué de votre équipe."], 403);
        }

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

        // Notification au délégué
        $delegate = $sampleRequest->user;
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Demande d\'Échantillons Approuvée',
                "Votre demande de réassort d'échantillons ({$sampleRequest->quantity} boîtes) a été approuvée par votre manager.",
                'sample',
                'delegate',
                ['id' => (string) $sampleRequest->id, 'type' => 'sample']
            );
        }

        return response()->json($sampleRequest->load(['product', 'user', 'approver']));
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isAdminOrSupervisor() && !$user->hasRole(['superviseur', 'directeur_marketing', 'superadmin', 'manager', 'Manager'])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $sampleRequest = SampleRequest::findOrFail($id);
        if (!(($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) || $user->manages($sampleRequest->user_id))) {
            return response()->json(['message' => "Cette demande n'appartient pas à un délégué de votre équipe."], 403);
        }

        if ($sampleRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande est déjà traitée.'], 400);
        }

        $sampleRequest->status = 'rejected';
        $sampleRequest->approved_by = $user->id;
        $sampleRequest->save();

        $delegate = $sampleRequest->user;
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Demande d\'Échantillons Refusée',
                "Votre demande de réassort d'échantillons a été refusée par votre manager.",
                'sample',
                'delegate',
                ['id' => (string) $sampleRequest->id, 'type' => 'sample']
            );
        }

        return response()->json($sampleRequest->load(['product', 'user', 'approver']));
    }
}
