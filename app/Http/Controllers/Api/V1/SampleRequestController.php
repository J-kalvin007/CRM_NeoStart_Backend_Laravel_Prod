<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleRequest;
use App\Models\SampleStock;
use App\Models\SampleMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SampleRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = SampleRequest::with(['product', 'user', 'approver'])
            ->orderBy('created_at', 'desc');

        // Users see their own requests and requests from their direct reports only.
        if (!($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']))) {
            $query->where(function ($requests) use ($user) {
                $requests->where('user_id', $user->id)
                    ->orWhereHas('user', fn ($delegate) => $delegate->withoutGlobalScopes()->where('manager_id', $user->id));
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['delegue_medical', 'delegue-medical', 'delegue', 'delegate'])) {
            return response()->json(['message' => 'Seul un délégué peut demander des échantillons.'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'notes' => 'required|string|max:2000',
        ]);

        $manager = $user->manager;
        if (!$manager) {
            return response()->json(['message' => 'Aucun supérieur direct n’est associé à votre compte.'], 422);
        }

        $product = \App\Models\Product::findOrFail($validated['product_id']);

        $sampleRequest = SampleRequest::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $validated['quantity'],
            'status' => 'pending',
            'notes' => $validated['notes'],
        ]);

        NotificationController::sendNotification(
            $manager,
            'Nouvelle demande d’échantillons',
            "{$user->name} demande {$validated['quantity']} boîte(s) de " . ($product->nom ?: $product->name) . ". Motif : {$validated['notes']}",
            'sample',
            'superviseur',
            ['request_id' => (string) $sampleRequest->id]
        );

        return response()->json($sampleRequest->load(['product', 'user', 'approver']), 201);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();

        $sampleRequest = SampleRequest::with('user')->findOrFail($id);
        $this->authorizeDecision($user, $sampleRequest);
        $validated = $request->validate([
            'quantity_delivered' => 'required|integer|min:1|max:' . $sampleRequest->quantity,
        ]);

        $sampleRequest = DB::transaction(function () use ($id, $user, $validated) {
            $sampleRequest = SampleRequest::with('user')->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($sampleRequest->status === 'pending', 409, 'Cette demande a déjà été traitée.');

            $delivered = (int) $validated['quantity_delivered'];
            $lotNumber = 'REQ-' . strtoupper(substr(str_replace('-', '', $sampleRequest->id), 0, 16));
            $stock = SampleStock::firstOrCreate([
                'user_id' => $sampleRequest->user_id,
                'product_id' => $sampleRequest->product_id,
                'lot_number' => $lotNumber,
            ], ['expiry_date' => null, 'quantity' => 0]);
            $stock->increment('quantity', $delivered);
            $stock->refresh();

            SampleMovement::create([
                'user_id' => $sampleRequest->user_id,
                'product_id' => $sampleRequest->product_id,
                'movement_type' => 'reception',
                'quantity' => $delivered,
                'stock_apres' => $stock->quantity,
                'remarques' => 'Remis par ' . $user->name . ' au titre de la demande ' . $sampleRequest->id,
                'lot_number' => $lotNumber,
            ]);

            $sampleRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_quantity' => $delivered,
                'rejection_reason' => null,
            ]);

            return $sampleRequest->fresh(['product', 'user', 'approver']);
        });

        // Notification au délégué
        $delegate = $sampleRequest->user;
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Demande d’échantillons approuvée',
                "Votre supérieur vous a remis {$sampleRequest->approved_quantity} boîte(s) de " . ($sampleRequest->product?->nom ?? 'ce produit') . ".",
                'sample',
                'delegate',
                ['request_id' => (string) $sampleRequest->id, 'type' => 'sample']
            );
        }

        return response()->json($sampleRequest);
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();

        $sampleRequest = SampleRequest::with('user')->findOrFail($id);
        $this->authorizeDecision($user, $sampleRequest);
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:3|max:2000',
        ]);

        $sampleRequest = DB::transaction(function () use ($id, $user, $validated) {
            $sampleRequest = SampleRequest::with('user')->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($sampleRequest->status === 'pending', 409, 'Cette demande a déjà été traitée.');

            $sampleRequest->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'approved_quantity' => null,
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return $sampleRequest->fresh(['product', 'user', 'approver']);
        });

        $delegate = $sampleRequest->user;
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Demande d’échantillons refusée',
                "Votre demande d’échantillons a été refusée par {$user->name}. Motif : {$validated['rejection_reason']}",
                'sample',
                'delegate',
                ['request_id' => (string) $sampleRequest->id, 'type' => 'sample', 'rejection_reason' => $validated['rejection_reason']]
            );
        }

        return response()->json($sampleRequest);
    }

    private function authorizeDecision($user, SampleRequest $sampleRequest): void
    {
        $isGlobalAdmin = $user->can('scope:global')
            || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        $canApproveSamples = $user->hasAnyRole([
            'superviseur', 'supervisor', 'sales_manager', 'manager', 'Manager',
            'directeur_marketing', 'directeur-marketing',
        ]);
        $isDirectManager = (string) $sampleRequest->user?->manager_id === (string) $user->id;

        abort_unless($isGlobalAdmin || ($canApproveSamples && $isDirectManager), 403, 'Seul le supérieur direct peut traiter cette demande.');
    }

    /**
     * Modifier sa demande d'échantillons tant qu'elle est en attente.
     */
    public function update(Request $request, $id)
    {
        $sampleRequest = $this->ownPendingRequest($id);

        $validated = $request->validate([
            'product_id' => 'nullable|uuid|exists:products,id',
            'quantity'   => 'nullable|integer|min:1',
            'notes'      => 'nullable|string',
        ]);

        $sampleRequest->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['message' => "Demande d'échantillons modifiée.", 'data' => $sampleRequest->fresh(['product'])]);
    }

    /**
     * Annuler sa demande d'échantillons tant qu'elle est en attente.
     */
    public function destroy($id)
    {
        $this->ownPendingRequest($id)->delete();

        return response()->json(['message' => "Demande d'échantillons annulée."]);
    }

    private function ownPendingRequest($id): SampleRequest
    {
        $sampleRequest = SampleRequest::findOrFail($id);
        abort_unless($sampleRequest->user_id === auth()->id(), 403, 'Vous ne pouvez modifier que vos propres demandes.');
        abort_unless($sampleRequest->status === 'pending', 422, 'Cette demande a déjà été traitée et ne peut plus être modifiée.');

        return $sampleRequest;
    }
}
