<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveController extends Controller
{
    /**
     * Liste des congés, absences et indisponibilités.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin', 'super-admin']) || $user->can('scope:global');
        $isManager = $user->hasAnyRole(['sales_manager', 'sales-manager', 'superviseur', 'supervisor', 'directeur_regional', 'manager']);

        $query = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->whereIn('activity_type', ['conge', 'absence', 'indisponibilite', 'leave', 'Congé Annuel', 'Congé Maladie'])
            ->with(['delegue', 'validatedBy'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->statut ?? $request->status, fn($q, $v) => $q->where('statut', $v))
            ->when($request->start_date ?? $request->date_debut, fn($q, $v) => $q->whereDate('date_activite', '>=', $v))
            ->when($request->end_date ?? $request->date_fin, fn($q, $v) => $q->whereDate('date_activite', '<=', $v))
            ->orderByDesc('date_activite');

        if (!$isGlobal) {
            $query->where(function ($q) use ($user, $isManager) {
                $q->where('user_id', $user->id);
                // La hiérarchie voit aussi les congés de ses collaborateurs encadrés
                $teamIds = $user->teamMemberIds();
                if (!empty($teamIds)) {
                    $q->orWhereIn('user_id', $teamIds);
                }
            });
        }

        $leaves = $query->get()->map(function ($act) {
            return [
                'id'               => $act->id,
                'user_id'          => $act->user_id,
                'employee_name'    => $act->delegue?->name ?? 'Délégué',
                'type'             => $act->activity_type,
                'activity_type'    => $act->activity_type,
                'start_date'       => $act->date_activite ? Carbon::parse($act->date_activite)->format('Y-m-d') : null,
                'end_date'         => $act->date_activite ? Carbon::parse($act->date_activite)->format('Y-m-d') : null,
                'date_activite'    => $act->date_activite ? Carbon::parse($act->date_activite)->format('Y-m-d') : null,
                'heure_debut'      => $act->heure_debut,
                'heure_fin'        => $act->heure_fin,
                'status'           => $act->statut,
                'statut'           => $act->statut,
                'reason'           => $act->notes,
                'motif'            => $act->notes,
                'rejection_reason' => $act->rejection_reason,
                'created_at'       => $act->created_at,
            ];
        });

        return response()->json([
            'data' => $leaves
        ]);
    }

    /**
     * Demander un congé / une indisponibilité.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'nullable|string',
            'type'          => 'nullable|string',
            'activity_type' => 'nullable|string',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date',
            'date_debut'    => 'nullable|date',
            'date_fin'      => 'nullable|date',
            'date_activite' => 'nullable|date',
            'heure_debut'   => 'nullable|string',
            'heure_fin'     => 'nullable|string',
            'reason'        => 'nullable|string',
            'motif'         => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $user = auth()->user();
        $targetUserId = $user->id;

        if (!empty($validated['user_id']) && ($user->hasAnyRole(['superadmin', 'admin', 'superviseur', 'manager']) || $user->can('create-others-activities'))) {
            $targetUserId = $validated['user_id'];
        }

        $startDateStr = $validated['start_date'] ?? $validated['date_debut'] ?? $validated['date_activite'] ?? now()->format('Y-m-d');
        $endDateStr   = $validated['end_date'] ?? $validated['date_fin'] ?? $startDateStr;
        $type = $validated['activity_type'] ?? $validated['type'] ?? 'conge';
        $heureDebut = $validated['heure_debut'] ?? null;
        $heureFin = $validated['heure_fin'] ?? null;
        $reason = $validated['reason'] ?? $validated['motif'] ?? $validated['notes'] ?? 'Demande de congé/indisponibilité';

        $startDate = Carbon::parse($startDateStr);
        $endDate   = Carbon::parse($endDateStr);

        if ($endDate->lt($startDate)) {
            return response()->json([
                'message' => 'La date de fin ne peut pas être antérieure à la date de début.',
                'errors'  => ['end_date' => ['La date de fin est invalide.']]
            ], 422);
        }

        // Vérification des chevauchements pour chaque jour de la plage de dates
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateFormatted = $currentDate->format('Y-m-d');
            $hasOverlap = Activity::checkOverlap($targetUserId, $dateFormatted, $heureDebut, $heureFin);

            if ($hasOverlap) {
                return response()->json([
                    'message' => 'Conflit avec un congé/une indisponibilité ou une visite existante sur ce créneau.',
                    'errors'  => ['date_activite' => ["Un conflit de planning existe le {$dateFormatted}."]]
                ], 422);
            }
            $currentDate->addDay();
        }

        $initialStatus = $user->hasAnyRole(['superadmin', 'admin', 'superviseur', 'manager']) ? 'validee' : 'en_attente';
        $createdActivities = [];

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $activity = Activity::create([
                'user_id'       => $targetUserId,
                'activity_type' => $type,
                'date_activite' => $currentDate->format('Y-m-d'),
                'heure_debut'   => $heureDebut,
                'heure_fin'     => $heureFin,
                'notes'         => $reason,
                'statut'        => $initialStatus,
            ]);
            $createdActivities[] = $activity;
            $currentDate->addDay();
        }

        $firstActivity = $createdActivities[0];

        // Notification au manager si créée par un délégué
        $manager = $user->manager ?? ($user->manager_id ? User::find($user->manager_id) : null);
        if ($manager && !$user->hasAnyRole(['superadmin', 'admin', 'superviseur', 'manager'])) {
            try {
                NotificationController::sendNotification(
                    $manager,
                    'Nouvelle demande de congé',
                    "Le délégué {$user->name} a soumis une demande de congé du {$startDateStr} au {$endDateStr}.",
                    'conge_soumis',
                    'manager',
                    ['activity_id' => (string) $firstActivity->id]
                );
            } catch (\Throwable $e) {}
        }

        $formattedResult = [
            'id'               => $firstActivity->id,
            'user_id'          => $firstActivity->user_id,
            'employee_name'    => $firstActivity->delegue?->name ?? $user->name,
            'type'             => $firstActivity->activity_type,
            'activity_type'    => $firstActivity->activity_type,
            'start_date'       => $startDateStr,
            'end_date'         => $endDateStr,
            'date_activite'    => $startDateStr,
            'heure_debut'      => $firstActivity->heure_debut,
            'heure_fin'        => $firstActivity->heure_fin,
            'status'           => $firstActivity->statut,
            'statut'           => $firstActivity->statut,
            'reason'           => $firstActivity->notes,
            'motif'            => $firstActivity->notes,
            'rejection_reason' => null,
            'created_at'       => $firstActivity->created_at,
        ];

        return response()->json([
            'message' => 'Demande de congé/indisponibilité créée avec succès.',
            'data'    => $formattedResult,
        ], 201);
    }

    /**
     * Mettre à jour le statut d'un congé (Validation / Rejet).
     */
    public function updateStatus(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
        $user = auth()->user();
        abort_unless(($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) || $user->manages($activity->user_id), 403, "Ce congé n'appartient pas à un délégué de votre équipe.");

        $validated = $request->validate([
            'status'           => 'nullable|string',
            'statut'           => 'nullable|string',
            'rejection_reason' => 'nullable|string',
            'motif'            => 'nullable|string',
        ]);

        $st = strtolower($validated['statut'] ?? $validated['status'] ?? '');
        $rejectionReason = $validated['rejection_reason'] ?? $validated['motif'] ?? null;

        $isRejection = in_array($st, ['rejetee', 'refusee', 'rejected']);

        if ($isRejection && (empty($rejectionReason) || strlen(trim($rejectionReason)) < 2)) {
            return response()->json([
                'message' => 'Le motif du rejet est obligatoire.',
                'errors'  => ['rejection_reason' => ['Le motif du rejet est obligatoire.']]
            ], 422);
        }

        $newStatut = $isRejection ? 'rejetee' : 'validee';

        $notes = $activity->notes;
        if ($isRejection && $rejectionReason) {
            $notes = $notes ? $notes . "\n[Rejeté]: " . $rejectionReason : "[Rejeté]: " . $rejectionReason;
        }

        $activity->update([
            'statut'           => $newStatut,
            'validated_by_id' => auth()->id(),
            'validation_date' => now(),
            'notes'            => $notes,
            'rejection_reason' => $rejectionReason,
        ]);

        // Notifier le délégué
        if ($activity->delegue) {
            $title = $newStatut === 'validee' ? 'Congé Validé' : 'Congé Refusé';
            $msg = $newStatut === 'validee'
                ? "Votre demande de congé pour le {$activity->date_activite} a été validée."
                : "Votre demande de congé pour le {$activity->date_activite} a été refusée. Motif : {$rejectionReason}";

            try {
                NotificationController::sendNotification(
                    $activity->delegue,
                    $title,
                    $msg,
                    'conge_decision',
                    'delegate',
                    ['activity_id' => (string) $activity->id]
                );
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'message' => 'Statut du congé mis à jour avec succès.',
            'data'    => [
                'id'               => $activity->id,
                'user_id'          => $activity->user_id,
                'employee_name'    => $activity->delegue?->name ?? 'Délégué',
                'type'             => $activity->activity_type,
                'activity_type'    => $activity->activity_type,
                'start_date'       => $activity->date_activite ? Carbon::parse($activity->date_activite)->format('Y-m-d') : null,
                'end_date'         => $activity->date_activite ? Carbon::parse($activity->date_activite)->format('Y-m-d') : null,
                'date_activite'    => $activity->date_activite ? Carbon::parse($activity->date_activite)->format('Y-m-d') : null,
                'status'           => $activity->statut,
                'statut'           => $activity->statut,
                'reason'           => $activity->notes,
                'motif'            => $activity->notes,
                'rejection_reason' => $activity->rejection_reason,
            ]
        ]);
    }

    /**
     * Valider une demande de congé.
     */
    public function validateLeave(Request $request, string $id)
    {
        $request->merge(['statut' => 'validee']);
        return $this->updateStatus($request, $id);
    }

    /**
     * Rejeter une demande de congé (motif obligatoire).
     */
    public function rejectLeave(Request $request, string $id)
    {
        $request->merge(['statut' => 'rejetee']);
        return $this->updateStatus($request, $id);
    }

    /**
     * Modifier sa demande de congé tant qu'elle est en attente (dates, horaires, motif, type).
     */
    public function update(Request $request, string $id)
    {
        $leave = $this->ownPendingLeave($id);

        $validated = $request->validate([
            'type'          => 'nullable|string',
            'activity_type' => 'nullable|string',
            'date_activite' => 'nullable|date',
            'heure_debut'   => 'nullable|string',
            'heure_fin'     => 'nullable|string',
            'reason'        => 'nullable|string',
            'motif'         => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $leave->update(array_filter([
            'activity_type' => $validated['activity_type'] ?? $validated['type'] ?? null,
            'date_activite' => $validated['date_activite'] ?? null,
            'heure_debut'   => $validated['heure_debut'] ?? null,
            'heure_fin'     => $validated['heure_fin'] ?? null,
            'notes'         => $validated['reason'] ?? $validated['motif'] ?? $validated['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['message' => 'Demande de congé modifiée.', 'data' => $leave->fresh()]);
    }

    /**
     * Annuler (supprimer) sa demande de congé tant qu'elle est en attente.
     */
    public function destroy(string $id)
    {
        $this->ownPendingLeave($id)->delete();

        return response()->json(['message' => 'Demande de congé annulée.']);
    }

    private function ownPendingLeave(string $id): Activity
    {
        $leave = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->whereIn('activity_type', ['conge', 'absence', 'indisponibilite', 'leave', 'Congé Annuel', 'Congé Maladie'])
            ->findOrFail($id);

        abort_unless($leave->user_id === auth()->id(), 403, 'Vous ne pouvez modifier que vos propres demandes.');
        abort_unless($leave->statut === 'en_attente', 422, 'Cette demande a déjà été traitée et ne peut plus être modifiée.');

        return $leave;
    }
}
