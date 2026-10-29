<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\NotificationController;
use App\Models\Activity;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceEntry;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveWorkflow
{
    public const COLORS = ['annual' => '#059669', 'sick' => '#dc2626', 'maternity' => '#0050CB', 'exceptional' => '#9333ea', 'other' => '#b45309'];

    public const TYPES = ['annual' => 'Congé annuel', 'sick' => 'Congé maladie', 'maternity' => 'Congé maternité', 'exceptional' => 'Permission exceptionnelle', 'other' => 'Autre permission'];

    public function global(User $user): bool
    {
        return $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);
    }

    public function finalDecider(User $user): bool
    {
        return $this->global($user) || $user->hasAnyRole(['dg', 'DG', 'rh', 'RH', 'hr', 'directeur_general']) || $user->can('leaves.approve-final') || $user->can('conges.approve');
    }

    public function visible(User $user)
    {
        return LeaveRequest::query()->when(! $this->global($user), function ($query) use ($user) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds()))
                    ->orWhereHas('steps', fn ($step) => $step->where('assignee_id', $user->id));
                if ($this->finalDecider($user)) {
                    $q->orWhereHas('user', fn ($owner) => $owner->withoutGlobalScope(DynamicPerimeterScope::class)->where('country_id', $user->country_id));
                }
            });
        });
    }

    public function currentStep(LeaveRequest $leave)
    {
        return $leave->steps->firstWhere('status', 'pending');
    }

    public function canDecide(User $actor, LeaveRequest $leave): bool
    {
        $step = $this->currentStep($leave);

        return $actor->id !== $leave->user_id && $leave->grant_status === 'pending' && $step &&
            ($step->assignee_id ? $step->assignee_id === $actor->id : ($step->action === 'final_grant' && $this->finalDecider($actor)));
    }

    public function canCancel(User $actor, LeaveRequest $leave): bool
    {
        return $actor->id === $leave->user_id && $leave->grant_status === 'pending' &&
            $leave->activity->date_activite->gte(now()->startOfDay()) && ! $leave->steps->contains(fn ($step) => $step->step > 1 && $step->status !== 'pending');
    }

    public function submit(Request $request, ?Activity $legacy = null): LeaveRequest
    {
        $values = $request->validate([
            'leave_type' => 'required|in:'.implode(',', array_keys(self::TYPES)), 'permission_code' => 'nullable|string|max:100',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'return_date' => 'required|date_format:Y-m-d|after:end_date', 'reference_year' => 'nullable|integer|min:2000|max:2100',
            'reason' => 'required|string|min:3|max:10000', 'interim_user_id' => 'nullable|uuid',
            'attachments' => 'sometimes|array|max:5', 'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);
        $owner = User::withoutGlobalScope(DynamicPerimeterScope::class)->findOrFail($request->user()->id);
        abort_if($request->filled('user_id') && $request->user_id !== $owner->id, 403, 'Soumettez uniquement votre propre demande.');
        abort_unless($owner->country_id, 422, 'Renseignez le pays du collaborateur avant de soumettre une demande RH.');
        $days = (int) Carbon::parse($values['start_date'])->diffInDays(Carbon::parse($values['end_date'])) + 1;
        $year = $values['reference_year'] ?? (int) substr($values['start_date'], 0, 4);
        abort_if($year > (int) substr($values['start_date'], 0, 4), 422, 'Le solde de référence ne peut pas être celui d’une année future.');
        abort_if($values['leave_type'] === 'sick' && ! $request->hasFile('attachments'), 422, 'Un certificat médical est obligatoire pour un congé maladie.');
        abort_if($values['leave_type'] === 'maternity' && ! $owner->maternity_leave_eligible, 422, 'L’éligibilité au congé maternité doit être renseignée par les RH.');
        $policy = LeavePolicy::where('country_id', $owner->country_id)->first();
        if ($values['leave_type'] === 'exceptional') {
            $rule = collect($policy?->permission_rules ?? [])->firstWhere('code', $values['permission_code'] ?? '');
            abort_unless($rule, 422, 'Choisissez un événement familial configuré pour votre pays.');
            abort_if($days > $rule['days'], 422, 'La durée dépasse le plafond configuré pour cet événement.');
            abort_if(($rule['requires_document'] ?? false) && ! $request->hasFile('attachments'), 422, 'Un justificatif est obligatoire pour cet événement.');
        }
        $interim = null;
        if (! empty($values['interim_user_id'])) {
            $interim = User::withoutGlobalScopes()->where('country_id', $owner->country_id)->where('status', 1)->findOrFail($values['interim_user_id']);
            abort_if($interim->id === $owner->id, 422, 'Le remplaçant doit être un autre collaborateur.');
        }
        $paths = [];
        try {
            $leave = DB::transaction(function () use ($request, $values, $owner, $days, $year, $policy, $interim, $legacy, &$paths) {
                User::withoutGlobalScopes()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                if ($legacy) {
                    $legacy = Activity::withoutGlobalScopes()->lockForUpdate()->findOrFail($legacy->id);
                    abort_unless($legacy->user_id === $owner->id && $legacy->statut === 'brouillon' && ! $legacy->leaveRequest()->exists(), 422, 'Cette absence ne peut plus être soumise.');
                }
                $this->assertAvailable($owner->id, $values['start_date'], $values['return_date'], $legacy?->id);
                $balance = LeaveBalance::where('user_id', $owner->id)->where('year', $year)->where('leave_type', $values['leave_type'])->lockForUpdate()->first();
                abort_unless($balance && $balance->remaining_days >= $days, 422, 'Solde insuffisant ou non renseigné pour cette année et ce type de congé.');
                $kind = in_array($values['leave_type'], ['exceptional', 'other']) ? 'permission' : 'conge';
                $activityValues = ['user_id' => $owner->id, 'activity_type' => $kind, 'activity_type_id' => null, 'date_activite' => $values['start_date'], 'date_fin' => $values['end_date'],
                    'heure_debut' => '00:00', 'heure_fin' => '23:59:59', 'notes' => $values['reason'], 'statut' => 'en_attente', 'tour_plan_id' => null];
                if ($legacy) {
                    $legacy->update($activityValues);
                    $activity = $legacy;
                } else {
                    $activity = Activity::create($activityValues);
                }
                $leave = LeaveRequest::create(['activity_id' => $activity->id, 'user_id' => $owner->id, 'leave_type' => $values['leave_type'],
                    'permission_code' => $values['permission_code'] ?? null, 'type_label' => self::TYPES[$values['leave_type']],
                    'return_date' => $values['return_date'], 'reference_year' => $year, 'absence_code' => $policy?->absence_codes[$values['leave_type']] ?? null,
                    'interim_user_id' => $interim?->id, 'days' => $days, 'submitted_at' => now()]);
                $this->buildSteps($leave, $owner);
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('leaves/'.$leave->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Le justificatif n’a pas pu être enregistré.');
                    }
                    $paths[] = $path;
                    $leave->documents()->create(['path' => $path, 'name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                }

                return $leave->load(['activity', 'steps', 'documents']);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($paths);
            throw $error;
        }
        $this->notify($leave, 'Demande soumise');

        return $leave;
    }

    private function buildSteps(LeaveRequest $leave, User $owner): void
    {
        $leave->steps()->create(['step' => 1, 'action' => 'submission', 'assignee_id' => $owner->id, 'assignee_name' => $owner->name, 'actor_id' => $owner->id, 'status' => 'completed', 'comment' => 'Demande soumise', 'acted_at' => now()]);
        $visited = [$owner->id];
        $chain = [];
        $person = $owner;
        while ($person->manager_id) {
            abort_if(in_array($person->manager_id, $visited, true), 422, 'La hiérarchie contient une boucle. Corrigez les responsables du collaborateur.');
            $person = User::withoutGlobalScopes()->find($person->manager_id);
            abort_unless($person && $person->status, 422, 'Un responsable hiérarchique est absent ou inactif.');
            $visited[] = $person->id;
            $chain[] = $person;
            if ($this->finalDecider($person)) {
                break;
            }
        }
        if ($owner->manager_fonctionnel_id && ! in_array($owner->manager_fonctionnel_id, $visited, true)) {
            $functional = User::withoutGlobalScopes()->find($owner->manager_fonctionnel_id);
            abort_unless($functional && $functional->status, 422, 'Le responsable fonctionnel est absent ou inactif.');
            array_splice($chain, max(0, count($chain) - ($chain && $this->finalDecider(end($chain)) ? 1 : 0)), 0, [$functional]);
        }
        $step = 2;
        $final = false;
        foreach ($chain as $manager) {
            $final = $this->finalDecider($manager);
            $leave->steps()->create(['step' => $step++, 'action' => $final ? 'final_grant' : 'hierarchical_approval', 'assignee_id' => $manager->id, 'assignee_name' => $manager->name]);
            if ($final) {
                break;
            }
        }
        if (! $final) {
            abort_if($this->finalRecipients($owner)->isEmpty(), 422, 'Aucun décideur final DG/RH ou administrateur actif n’est configuré.');
            $leave->steps()->create(['step' => $step, 'action' => 'final_grant', 'assignee_name' => 'Direction / RH']);
        }
    }

    private function finalRecipients(User $owner)
    {
        return User::withoutGlobalScopes()->with(['roles','permissions'])->where('status',1)->where('id','!=',$owner->id)
            ->get()->filter(fn($user)=>$this->finalDecider($user) && ($this->global($user) || $user->country_id===$owner->country_id));
    }

    public function assertAvailable(string $userId, string $start, string $end, ?string $exclude = null): void
    {
        $overlap = LeaveRequest::withoutGlobalScopes()->where('user_id', $userId)->whereIn('grant_status', ['pending', 'granted'])
            ->whereDate('return_date', '>=', $start)->whereHas('activity', fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->whereDate('date_activite', '<=', $end)->when($exclude, fn ($q) => $q->where('id', '!=', $exclude)))->exists();
        abort_if($overlap || Activity::checkOverlap($userId, $start, '00:00', '23:59:59', null, $exclude, true, $end), 422, 'Cette période chevauche une absence ou une activité existante (retour inclus).');
    }

    public function decide(User $actor, string $activityId, bool $approved, string $note): LeaveRequest
    {
        $leave = $this->visible($actor)->where('activity_id', $activityId)->firstOrFail();
        $leave = DB::transaction(function () use ($leave, $actor, $approved, $note) {
            // All submissions, decisions and balance adjustments lock the owner first.
            User::withoutGlobalScopes()->whereKey($leave->user_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail()->load(['activity', 'steps']);
            abort_unless($this->canDecide($actor, $leave), 403, 'Cette étape ne vous est pas attribuée ou la demande a déjà été traitée.');
            $step = $this->currentStep($leave);
            if ($approved && $step->action === 'final_grant') {
                $this->assertAvailable($leave->user_id, $leave->activity->date_activite->toDateString(), $leave->return_date->toDateString(), $leave->activity_id);
                $balance = LeaveBalance::where('user_id', $leave->user_id)->where('year', $leave->reference_year)->where('leave_type', $leave->leave_type)->lockForUpdate()->first();
                abort_unless($balance && $balance->remaining_days >= $leave->days, 422, 'Le solde est désormais insuffisant. Aucun jour n’a été débité.');
                $balance->decrement('remaining_days', $leave->days);
                $balance->refresh();
                LeaveBalanceEntry::create(['leave_balance_id' => $balance->id, 'leave_request_id' => $leave->id, 'actor_id' => $actor->id, 'action' => 'final_grant', 'days' => -$leave->days, 'remaining_after' => $balance->remaining_days, 'reason' => $note]);
                $leave->update(['grant_status' => 'granted', 'debited_at' => now()]);
                $leave->activity->update(['statut' => 'validee', 'validated_by_id' => $actor->id, 'validation_date' => now(), 'rejection_reason' => null]);
            } elseif (! $approved) {
                $leave->update(['grant_status' => 'rejected']);
                $leave->steps()->where('status', 'pending')->where('id', '!=', $step->id)->update(['status' => 'skipped']);
                $leave->activity->update(['statut' => 'refusee', 'rejection_reason' => $note, 'validated_by_id' => $actor->id, 'validation_date' => now()]);
            }
            $leave->activity->touch();
            $step->update(['status' => $approved ? 'completed' : 'rejected', 'actor_id' => $actor->id, 'comment' => $note, 'acted_at' => now()]);

            return $leave->fresh(['activity', 'steps', 'documents']);
        });
        $this->notify($leave, $approved ? ($leave->grant_status === 'granted' ? 'Accord définitif' : 'Avis hiérarchique favorable') : 'Demande refusée');

        return $leave;
    }

    public function cancel(User $actor, string $id): LeaveRequest
    {
        $leave = $this->visible($actor)->where('activity_id', $id)->firstOrFail();
        $leave = DB::transaction(function () use ($leave, $actor) {
            User::withoutGlobalScopes()->whereKey($leave->user_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail()->load(['activity', 'steps']);
            abort_unless($this->canCancel($actor, $leave), 422, 'Annulation impossible : une décision a déjà été prise ou la période a débuté.');
            $leave->update(['grant_status' => 'cancelled']);
            $leave->steps()->where('status', 'pending')->update(['status' => 'skipped']);
            $leave->activity->update(['statut' => 'annulee']);
            $leave->steps()->create(['step' => $leave->steps->max('step') + 1, 'action' => 'cancellation', 'assignee_name' => $actor->name, 'actor_id' => $actor->id, 'status' => 'completed', 'comment' => 'Annulée par le demandeur', 'acted_at' => now()]);

            return $leave->fresh(['activity', 'steps', 'documents']);
        });
        $this->notify($leave, 'Demande annulée');

        return $leave;
    }

    public function present(Activity $activity, User $viewer): array
    {
        $start = $activity->date_activite->toDateString();
        $end = ($activity->date_fin ?? $activity->date_activite)->toDateString();
        $leave = $activity->leaveRequest;
        $data = ['id' => $activity->id, 'user_id' => $activity->user_id, 'employee_name' => $activity->delegue?->name,
            'type' => $activity->planning_kind, 'activity_type' => $activity->planning_kind, 'planning_kind' => $activity->planning_kind, 'type_label' => $leave?->type_label ?? $activity->activity_type_name,
            'start_date' => $start, 'end_date' => $end, 'date_activite' => $start, 'date_fin' => $end, 'heure_debut' => $activity->heure_debut, 'heure_fin' => $activity->heure_fin,
            'days' => $leave?->days ?? (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1, 'days_count' => $leave?->days ?? (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1,
            'status' => $activity->statut, 'statut' => $activity->statut, 'reason' => $activity->notes, 'motif' => $activity->notes, 'rejection_reason' => $activity->rejection_reason,
            'tour_plan_id' => $activity->tour_plan_id, 'created_at' => $activity->created_at, 'workflow_managed' => (bool) $leave];
        if (! $leave) {
            return $data;
        }
        $leave->loadMissing(['steps.actor', 'documents', 'interim']);

        return array_merge($data, ['leave_type' => $leave->leave_type, 'permission_code' => $leave->permission_code, 'return_date' => $leave->return_date->toDateString(),
            'reference_year' => $leave->reference_year, 'absence_code' => $leave->absence_code, 'interim_user_id' => $leave->interim_user_id, 'interim_name' => $leave->interim?->name,
            'grant_status' => $leave->grant_status, 'submitted_at' => $leave->submitted_at, 'debited_at' => $leave->debited_at,
            'workflow' => $leave->steps->map(fn ($s) => ['id' => $s->id, 'step' => $s->step, 'action' => $s->action, 'assignee_id' => $s->assignee_id, 'assignee_name' => $s->assignee_name, 'actor_id' => $s->actor_id, 'actor_name' => $s->actor?->name, 'status' => $s->status, 'comment' => $s->comment, 'date' => $s->acted_at]),
            'documents' => $viewer->id === $leave->user_id || $this->canDecide($viewer, $leave) || $this->finalDecider($viewer) ? $leave->documents : [],
            'can_decide' => $this->canDecide($viewer, $leave), 'can_cancel' => $this->canCancel($viewer, $leave), 'coverage_warning' => $this->coverage($leave)]);
    }

    public function coverage(LeaveRequest $leave): ?array
    {
        $owner = User::withoutGlobalScopes()->find($leave->user_id);
        if (! $owner?->field_post_id) {
            return null;
        }
        $ids = User::withoutGlobalScopes()->where('country_id', $owner->country_id)->where('field_post_id', $owner->field_post_id)->where('status', 1)->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }
        $absent = Activity::withoutGlobalScopes()->whereNull('deleted_at')->calendar(['conge', 'permission'])->whereIn('user_id', $ids)
            ->whereIn('statut', ['validee', 'en_attente'])->whereDate('date_activite', '<=', $leave->return_date)
            ->where(fn ($q) => $q->whereDate('date_fin', '>=', $leave->activity->date_activite)->orWhereHas('leaveRequest', fn ($l) => $l->whereDate('return_date', '>=', $leave->activity->date_activite)))
            ->get(['user_id', 'date_activite', 'date_fin', 'id']);
        $events = [];
        $windowStart = $leave->activity->date_activite->toDateString();
        $windowEnd = $leave->return_date->toDateString();
        $intervals = [];
        foreach ($absent as $activity) {
            $start = max($windowStart, $activity->date_activite->toDateString());
            $end = min($windowEnd, ($activity->leaveRequest?->return_date ?? $activity->date_fin ?? $activity->date_activite)->toDateString());
            if ($start > $end) {
                continue;
            }
            $events[$start] ??= [];
            $events[$start][] = ['user' => $activity->user_id, 'delta' => 1];
            $after = Carbon::parse($end)->addDay()->toDateString();
            $events[$after] ??= [];
            $events[$after][] = ['user' => $activity->user_id, 'delta' => -1];
        }
        ksort($events);
        $active = [];
        $peak = 0;
        foreach ($events as $changes) {
            foreach ($changes as $change) {
                $active[$change['user']] = ($active[$change['user']] ?? 0) + $change['delta'];
            }
            $peak = max($peak, count(array_filter($active, fn ($count) => $count > 0)));
        }

        return ['team_size' => $ids->count(), 'simultaneously_absent' => $peak, 'percent' => round($peak * 100 / $ids->count(), 1), 'warning' => $peak * 2 > $ids->count()];
    }

    private function notify(LeaveRequest $leave, string $title): void
    {
        $owner = User::withoutGlobalScopes()->find($leave->user_id);
        $recipients = collect([$owner]);
        $step = $this->currentStep($leave);
        if ($leave->grant_status === 'pending' && $step) {
            $recipients = $recipients->merge($step->assignee_id ? [User::withoutGlobalScopes()->find($step->assignee_id)] : $this->finalRecipients($owner));
        }
        // Also inform previously assigned evaluators on cancellation/rejection.
        if (in_array($leave->grant_status, ['cancelled', 'rejected'])) {
            $recipients = $recipients->merge(User::withoutGlobalScopes()->whereIn('id', $leave->steps->pluck('assignee_id')->filter())->get());
        }
        foreach ($recipients->filter()->unique('id') as $recipient) {
            try {
                NotificationController::sendNotification($recipient, $title, $owner->name.' · '.$leave->type_label, 'leave', 'all', ['id' => $leave->activity_id, 'entity_type' => 'leave', 'action_url' => config('app.frontend_url') ? rtrim(config('app.frontend_url'), '/').'/conges?leave='.$leave->activity_id : null]);
            } catch (\Throwable $error) {
                report($error);
            }

        }
    }
}
