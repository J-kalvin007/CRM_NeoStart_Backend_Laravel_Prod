<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\LeaveRequest;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Services\LeaveWorkflow;
use App\Services\PlanningOptions;
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

        $query = Activity::withoutGlobalScope(DynamicPerimeterScope::class)
            ->calendar(['conge', 'permission'])
            ->where(fn ($q) => $q->whereNotIn('statut', ['brouillon', 'draft'])->orWhere('user_id', $user->id))
            ->with(['delegue', 'validatedBy', 'leaveRequest.steps.actor', 'leaveRequest.documents', 'leaveRequest.interim'])
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->statut ?? $request->status, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->start_date ?? $request->date_debut, fn ($q, $v) => $q->whereRaw('COALESCE(date_fin, date_activite) >= ?', [$v]))
            ->when($request->end_date ?? $request->date_fin, fn ($q, $v) => $q->whereDate('date_activite', '<=', $v))
            ->orderByDesc('date_activite');

        if (! $isGlobal) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereIn('activities.id', app(LeaveWorkflow::class)->visible($user)->select('activity_id'));
                // La hiérarchie voit aussi les congés de ses collaborateurs encadrés
                $teamIds = $user->teamMemberIds();
                if (! empty($teamIds)) {
                    $q->orWhereIn('user_id', $teamIds);
                }
            });
        }

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        }
        $leaves = $query->get()->map(fn (Activity $activity) => $this->serializeLeave($activity));

        if ($request->boolean('pending_for_me')) {
            $leaves = $leaves->filter(fn ($row) => $row['can_decide'] ?? false)->values();
        }

        return response()->json([
            'data' => $leaves,
        ]);
    }

    /**
     * Demander un congé / une indisponibilité.
     */
    public function store(Request $request)
    {
        if ($request->filled('leave_type')) {
            $workflow = app(LeaveWorkflow::class);
            $leave = $workflow->submit($request);

            return response()->json(['data' => $workflow->present($leave->activity, $request->user()), 'message' => 'Demande soumise au circuit hiérarchique.'], 201);
        }
        abort(422, 'Utilisez le formulaire RH Congés & Permissions : type de demande, date de retour et année du solde sont obligatoires.');
    }

    /**
     * Mettre à jour le statut d'un congé (Validation / Rejet).
     */
    public function updateStatus(Request $request, string $id)
    {
        if (LeaveRequest::where('activity_id', $id)->exists()) {
            $status = $request->input('statut') ?? $request->input('status');
            abort_unless(in_array($status, ['validee', 'approved', 'refusee', 'rejected', 'rejetee']), 422, 'Décision invalide.');
            $request->merge(['decision' => in_array($status, ['validee', 'approved']) ? 'approve' : 'reject', 'motif' => $request->input('motif') ?? $request->input('rejection_reason')]);

            return app(LeaveWorkflowController::class)->decide($request, $id, app(LeaveWorkflow::class));
        }
        $activity = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->findOrFail($id);
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
            'type' => 'nullable|string',
            'activity_type' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'date_activite' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'heure_debut' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'heure_fin' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'reason' => 'nullable|string',
            'motif' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $start = Carbon::parse($validated['start_date'] ?? $validated['date_activite'] ?? $leave->date_activite);
        $end = Carbon::parse($validated['end_date'] ?? $validated['date_fin'] ?? $leave->date_fin ?? $start);
        abort_if($end->lt($start), 422, 'La fin doit être postérieure ou égale au début.');
        $this->validateTimes($start, $end, $validated['heure_debut'] ?? $leave->heure_debut, $validated['heure_fin'] ?? $leave->heure_fin);
        if (isset($validated['type']) || isset($validated['activity_type'])) {
            $kind = PlanningOptions::kind($validated['activity_type'] ?? $validated['type']);
            abort_unless(in_array($kind, ['conge', 'permission'], true), 422, 'Type de demande invalide.');
            abort_if(ActivityType::where('code', $kind)->where('is_active', false)->exists(), 422, 'Ce type d’activité est désactivé.');
        }
        abort_if(Activity::checkOverlap((string) $leave->user_id, $start->toDateString(),
            $validated['heure_debut'] ?? $leave->heure_debut, $validated['heure_fin'] ?? $leave->heure_fin,
            null, (string) $leave->id, true, $end->toDateString()), 422, 'Cette période chevauche une activité existante.');
        $leave->update(array_filter([
            'activity_type' => $validated['activity_type'] ?? $validated['type'] ?? null,
            'activity_type_id' => isset($validated['type']) || isset($validated['activity_type'])
                ? ActivityType::where('code', PlanningOptions::kind($validated['activity_type'] ?? $validated['type']))->value('id') : $leave->activity_type_id,
            'date_activite' => $start->toDateString(),
            'date_fin' => $end->toDateString(),
            'heure_debut' => $validated['heure_debut'] ?? null,
            'heure_fin' => $validated['heure_fin'] ?? null,
            'notes' => $validated['reason'] ?? $validated['motif'] ?? $validated['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['message' => 'Demande de congé modifiée.', 'data' => $this->serializeLeave($leave->fresh())]);
    }

    /**
     * Annuler (supprimer) sa demande de congé tant qu'elle est en attente.
     */
    public function destroy(string $id)
    {
        $workflow = app(LeaveWorkflow::class);
        if (LeaveRequest::where('activity_id', $id)->exists()) {
            $leave = $workflow->cancel(auth()->user(), $id);

            return response()->json(['message' => 'Demande annulée.', 'data' => $workflow->present($leave->activity->load('leaveRequest'), auth()->user())]);
        }
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
        return app(LeaveWorkflow::class)->present($activity, auth()->user());
    }

    private function ownPendingLeave(string $id): Activity
    {
        $leave = Activity::withoutGlobalScope(DynamicPerimeterScope::class)
            ->calendar(['conge', 'permission'])
            ->findOrFail($id);

        abort_unless($leave->user_id === auth()->id(), 403, 'Vous ne pouvez modifier que vos propres demandes.');
        abort_unless($leave->statut === 'brouillon', 422, 'Cette activité a déjà été soumise et ne peut plus être modifiée.');

        return $leave;
    }
}
