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
            ->calendar(['conge', 'permission'])
            ->with(['delegue', 'validatedBy'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->statut ?? $request->status, fn($q, $v) => $q->where('statut', $v))
            ->when($request->start_date ?? $request->date_debut, fn($q, $v) => $q->whereRaw('COALESCE(date_fin, date_activite) >= ?', [$v]))
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

        $leaves = $query->get()->map(fn (Activity $activity) => $this->serializeLeave($activity));

        return response()->json([
            'data' => $leaves
        ]);
    }

    /**
     * Demander un congé / une indisponibilité.
     */
    public function store(Request $request)
    {
        $request->merge([
            'start_date' => $request->input('start_date') ?? $request->input('date_debut') ?? $request->input('date_activite'),
            'end_date' => $request->input('end_date') ?? $request->input('date_fin') ?? $request->input('start_date') ?? $request->input('date_debut') ?? $request->input('date_activite'),
        ]);
        $validated = $request->validate([
            'user_id'       => 'nullable|string',
            'type'          => 'nullable|string',
            'activity_type' => 'nullable|string',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'date_debut'    => 'nullable|date',
            'date_fin'      => 'nullable|date',
            'date_activite' => 'nullable|date',
            'heure_debut'   => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'heure_fin'     => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
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

        abort_unless((string) $targetUserId === (string) $user->id || $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']) || $user->manages($targetUserId), 403);
        abort_if(Activity::checkOverlap($targetUserId, $startDateStr, $heureDebut, $heureFin, null, null, true, $endDateStr), 422, 'Cette période chevauche une activité existante.');

        $this->validateTimes($startDate, $endDate, $heureDebut ?? '08:00', $heureFin ?? '18:00');
        $initialStatus = 'brouillon';

        $typeRecord = \App\Models\ActivityType::where('code', \App\Services\PlanningOptions::kind($type))->first();
        abort_unless(in_array(\App\Services\PlanningOptions::kind($type), ['conge', 'permission'], true), 422, 'Type de demande invalide.');
        abort_if($typeRecord && !$typeRecord->is_active, 422, 'Ce type d’activité est désactivé.');
        $firstActivity = Activity::create([
            'user_id' => $targetUserId, 'activity_type' => $type,
            'activity_type_id' => $typeRecord?->id,
            'date_activite' => $startDate->toDateString(), 'date_fin' => $endDate->toDateString(),
            'heure_debut' => $heureDebut ?? '08:00', 'heure_fin' => $heureFin ?? '18:00',
            'notes' => $reason, 'statut' => $initialStatus,
        ]);

        $formattedResult = $this->serializeLeave($firstActivity);

        return response()->json([
            'message' => 'Activité enregistrée en brouillon. Soumettez votre planning hebdomadaire pour validation.',
            'data'    => $formattedResult,
        ], 201);
    }

    /**
     * Mettre à jour le statut d'un congé (Validation / Rejet).
     */
    public function updateStatus(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
        abort_unless(in_array($activity->planning_kind, ['conge', 'permission'], true), 422, 'Cette activité n’est pas une demande de congé ou de permission.');
        $user = auth()->user();
        abort_unless(($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) || $user->manages($activity->user_id), 403, "Ce congé n'appartient pas à un délégué de votre équipe.");

        abort(422, 'Les congés et permissions sont validés avec leur planning hebdomadaire.');
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

        $request->merge([
            'start_date' => $request->input('start_date') ?? $request->input('date_debut') ?? $request->input('date_activite'),
            'end_date' => $request->input('end_date') ?? $request->input('date_fin'),
        ]);
        $validated = $request->validate([
            'type'          => 'nullable|string',
            'activity_type' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'date_activite' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'heure_debut'   => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'heure_fin'     => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'reason'        => 'nullable|string',
            'motif'         => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $start = Carbon::parse($validated['start_date'] ?? $validated['date_activite'] ?? $leave->date_activite);
        $end = Carbon::parse($validated['end_date'] ?? $validated['date_fin'] ?? $leave->date_fin ?? $start);
        abort_if($end->lt($start), 422, 'La fin doit être postérieure ou égale au début.');
        $this->validateTimes($start, $end, $validated['heure_debut'] ?? $leave->heure_debut, $validated['heure_fin'] ?? $leave->heure_fin);
        if (isset($validated['type']) || isset($validated['activity_type'])) {
            $kind = \App\Services\PlanningOptions::kind($validated['activity_type'] ?? $validated['type']);
            abort_unless(in_array($kind, ['conge', 'permission'], true), 422, 'Type de demande invalide.');
            abort_if(\App\Models\ActivityType::where('code', $kind)->where('is_active', false)->exists(), 422, 'Ce type d’activité est désactivé.');
        }
        abort_if(Activity::checkOverlap((string) $leave->user_id, $start->toDateString(),
            $validated['heure_debut'] ?? $leave->heure_debut, $validated['heure_fin'] ?? $leave->heure_fin,
            null, (string) $leave->id, true, $end->toDateString()), 422, 'Cette période chevauche une activité existante.');
        $leave->update(array_filter([
            'activity_type' => $validated['activity_type'] ?? $validated['type'] ?? null,
            'activity_type_id' => isset($validated['type']) || isset($validated['activity_type'])
                ? \App\Models\ActivityType::where('code', \App\Services\PlanningOptions::kind($validated['activity_type'] ?? $validated['type']))->value('id') : $leave->activity_type_id,
            'date_activite' => $start->toDateString(),
            'date_fin' => $end->toDateString(),
            'heure_debut'   => $validated['heure_debut'] ?? null,
            'heure_fin'     => $validated['heure_fin'] ?? null,
            'notes'         => $validated['reason'] ?? $validated['motif'] ?? $validated['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['message' => 'Demande de congé modifiée.', 'data' => $this->serializeLeave($leave->fresh())]);
    }

    /**
     * Annuler (supprimer) sa demande de congé tant qu'elle est en attente.
     */
    public function destroy(string $id)
    {
        $this->ownPendingLeave($id)->delete();

        return response()->json(['message' => 'Demande de congé annulée.']);
    }

    private function validateTimes(Carbon $start, Carbon $end, ?string $startTime, ?string $endTime): void
    {
        if ($start->isSameDay($end) && $startTime && $endTime) {
            abort_if(substr($endTime, 0, 5) <= substr($startTime, 0, 5), 422, 'L’heure de fin doit être postérieure à l’heure de début.');
        }
    }

    private function serializeLeave(Activity $activity): array
    {
        $start = Carbon::parse($activity->date_activite)->toDateString();
        $end = Carbon::parse($activity->date_fin ?? $activity->date_activite)->toDateString();
        $days = (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        return [
            'id' => $activity->id, 'user_id' => $activity->user_id,
            'employee_name' => $activity->delegue?->name,
            'type' => $activity->planning_kind, 'activity_type' => $activity->planning_kind,
            'type_label' => $activity->activity_type_name, 'planning_kind' => $activity->planning_kind,
            'start_date' => $start, 'end_date' => $end,
            'date_activite' => $start, 'date_fin' => $end,
            'heure_debut' => $activity->heure_debut, 'heure_fin' => $activity->heure_fin,
            'days' => $days, 'days_count' => $days,
            'status' => $activity->statut, 'statut' => $activity->statut,
            'reason' => $activity->notes, 'motif' => $activity->notes,
            'rejection_reason' => $activity->rejection_reason,
            'tour_plan_id' => $activity->tour_plan_id, 'created_at' => $activity->created_at,
        ];
    }

    private function ownPendingLeave(string $id): Activity
    {
        $leave = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->calendar(['conge', 'permission'])
            ->findOrFail($id);

        abort_unless($leave->user_id === auth()->id(), 403, 'Vous ne pouvez modifier que vos propres demandes.');
        abort_unless($leave->statut === 'brouillon', 422, 'Cette activité a déjà été soumise et ne peut plus être modifiée.');

        return $leave;
    }
}
