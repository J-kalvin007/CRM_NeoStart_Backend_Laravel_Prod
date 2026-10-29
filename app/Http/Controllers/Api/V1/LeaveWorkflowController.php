<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceEntry;
use App\Models\LeaveDocument;
use App\Models\LeavePolicy;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\User;
use App\Services\LeaveWorkflow;
use App\Support\CountryFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveWorkflowController extends Controller
{
    public function options(Request $request, LeaveWorkflow $workflow)
    {
        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);
        $owner = $request->user();
        $country = CountryFilter::resolveCountryId() ?? $owner->country_id;

        return response()->json(['country_id' => $country, 'types' => collect(LeaveWorkflow::TYPES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label, 'color' => LeaveWorkflow::COLORS[$code]])->values(),
            'policy' => LeavePolicy::where('country_id', $country)->first(),
            'balances' => LeaveBalance::where('user_id', $owner->id)->where('year', $request->integer('year', (int) now()->year))->get(),
            'interims' => User::withoutGlobalScope(DynamicPerimeterScope::class)->where('country_id', $country)->where('status', 1)->where('id', '!=', $owner->id)->orderBy('name')->get(['id', 'name']),
            'external_email_enabled' => ! in_array(config('mail.default'), ['log', 'array'], true), 'can_manage' => $workflow->finalDecider($owner), 'maternity_eligible' => (bool) $owner->maternity_leave_eligible]);
    }

    public function submit(Request $request, string $id, LeaveWorkflow $workflow)
    {
        $activity = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->calendar(['conge', 'permission'])->findOrFail($id);
        abort_unless($activity->user_id === $request->user()->id, 403);
        $leave = $workflow->submit($request, $activity);

        return response()->json(['data' => $workflow->present($leave->activity, $request->user()), 'message' => 'Demande RH soumise.']);
    }

    public function decide(Request $request, string $id, LeaveWorkflow $workflow)
    {
        $values = $request->validate(['decision' => 'required|in:approve,reject,valider,rejeter', 'motif' => 'required|string|min:3|max:4000']);
        $leave = $workflow->decide($request->user(), $id, in_array($values['decision'], ['approve', 'valider']), $values['motif']);

        return response()->json(['data' => $workflow->present($leave->activity->load('leaveRequest'), $request->user())]);
    }

    public function show(Request $request, string $id, LeaveWorkflow $workflow)
    {
        $leave = $workflow->visible($request->user())->where('activity_id', $id)->with(['activity.leaveRequest.steps.actor', 'activity.delegue', 'activity.leaveRequest.documents'])->firstOrFail();

        return response()->json(['data' => $workflow->present($leave->activity, $request->user())]);
    }

    public function calendar(Request $request, LeaveWorkflow $workflow)
    {
        $values = $request->validate(['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after:start']);
        $requests = $workflow->visible($request->user())->where('grant_status', 'granted')->with(['activity', 'user'])
            ->whereDate('return_date', '>=', $values['start'])->whereHas('activity', fn ($q) => $q->withoutGlobalScope(DynamicPerimeterScope::class)->whereDate('date_activite', '<', $values['end']))->get();
        $colors = ['annual' => '#059669', 'sick' => '#dc2626', 'maternity' => '#0050CB', 'exceptional' => '#9333ea', 'other' => '#b45309'];
        $events = $requests->map(fn ($leave) => ['id' => $leave->activity_id, 'title' => $leave->user?->name.' · '.$leave->type_label,
            'start' => $leave->activity->date_activite->toDateString(), 'end' => $leave->return_date->copy()->addDay()->toDateString(), 'allDay' => true, 'color' => $colors[$leave->leave_type],
            'extendedProps' => ['leave_type' => $leave->leave_type, 'status' => $leave->activity->statut, 'days' => $leave->days, 'return_date' => $leave->return_date->toDateString()]]);
        $legacy = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->calendar(['conge', 'permission'])->whereDoesntHave('leaveRequest')->where('statut', 'validee')
            ->when(! $workflow->global($request->user()), fn ($q) => $q->whereIn('user_id', array_merge([$request->user()->id], $request->user()->teamMemberIds())))
            ->whereDate('date_activite', '<', $values['end'])->whereRaw('COALESCE(date_fin,date_activite) >= ?', [$values['start']])->with('delegue')->get();
        $events = $events->concat($legacy->map(fn ($activity) => ['id' => $activity->id, 'title' => $activity->delegue?->name.' · '.$activity->activity_type_name.' (historique)',
            'start' => $activity->date_activite->toDateString(), 'end' => ($activity->date_fin ?? $activity->date_activite)->copy()->addDay()->toDateString(), 'allDay' => true, 'color' => '#64748b', 'extendedProps' => ['legacy' => true]]));

        return response()->json(['data' => $events->values()]);
    }

    private function hr(Request $request, LeaveWorkflow $workflow): void
    {
        abort_unless($workflow->finalDecider($request->user()), 403);
    }

    private function employee(Request $request, LeaveWorkflow $workflow, string $id): User
    {
        $owner = User::withoutGlobalScope(DynamicPerimeterScope::class)->findOrFail($id);
        abort_unless($workflow->global($request->user()) || $owner->country_id === $request->user()->country_id, 403);

        return $owner;
    }

    public function employees(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);

        return response()->json(['data' => User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->when(! $workflow->global($request->user()), fn ($q) => $q->where('country_id', $request->user()->country_id))
            ->orderBy('name')->get(['id', 'name', 'country_id', 'manager_id', 'manager_fonctionnel_id', 'maternity_leave_eligible'])]);
    }

    public function employeeSettings(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);
        $values = $request->validate(['user_id' => 'required|uuid', 'manager_fonctionnel_id' => 'present|nullable|uuid', 'maternity_eligible' => 'required|boolean', 'reason' => 'required|string|min:3|max:4000']);
        $owner = $this->employee($request, $workflow, $values['user_id']);
        if ($values['manager_fonctionnel_id']) {
            $manager = $this->employee($request, $workflow, $values['manager_fonctionnel_id']);
            abort_unless($manager->id !== $owner->id && $manager->country_id === $owner->country_id && $manager->status, 422, 'Choisissez un responsable actif du même pays, distinct du collaborateur.');
        }
        $owner->forceFill(['manager_fonctionnel_id' => $values['manager_fonctionnel_id'], 'maternity_leave_eligible' => $values['maternity_eligible']])->save();
        activity()->causedBy($request->user())->performedOn($owner)->withProperties($values)->log('Paramètres RH du collaborateur');

        return response()->json(['message' => 'Paramètres RH enregistrés.']);
    }

    public function balances(Request $request, LeaveWorkflow $workflow)
    {
        $values = $request->validate(['user_id' => 'nullable|uuid', 'year' => 'required|integer|min:2000|max:2100']);
        $id = $values['user_id'] ?? $request->user()->id;
        if ($id !== $request->user()->id) {
            $this->hr($request, $workflow);
        }
        $this->employee($request, $workflow, $id);

        return response()->json(['data' => LeaveBalance::where('user_id', $id)->where('year', $values['year'])->with(['entries.actor:id,name'])->get(), 'maternity_eligible' => (bool) User::withoutGlobalScopes()->findOrFail($id)->maternity_leave_eligible]);
    }

    public function adjustBalance(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);
        $values = $request->validate(['user_id' => 'required|uuid', 'year' => 'required|integer|min:2000|max:2100', 'leave_type' => 'required|in:'.implode(',', array_keys(LeaveWorkflow::TYPES)),
            'remaining_days' => 'required|numeric|min:0|max:9999', 'reason' => 'required|string|min:3|max:4000', 'maternity_eligible' => 'sometimes|boolean']);
        $owner = $this->employee($request, $workflow, $values['user_id']);
        $balance = DB::transaction(function () use ($values, $owner, $request) {
            $owner = User::withoutGlobalScopes()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('maternity_eligible', $values)) {
                $owner->forceFill(['maternity_leave_eligible' => $values['maternity_eligible']])->save();
            }
            abort_if($values['leave_type'] === 'maternity' && ! $owner->maternity_leave_eligible, 422, 'Vérifiez et renseignez l’éligibilité au congé maternité.');
            $balance = LeaveBalance::firstOrCreate(['user_id' => $owner->id, 'year' => $values['year'], 'leave_type' => $values['leave_type']]);
            $delta = round($values['remaining_days'] - $balance->remaining_days, 2);
            $balance->update(['remaining_days' => $values['remaining_days'], 'initial_days' => $balance->initial_days + $delta]);
            LeaveBalanceEntry::create(['leave_balance_id' => $balance->id, 'actor_id' => $request->user()->id, 'action' => 'adjustment', 'days' => $delta, 'remaining_after' => $balance->remaining_days, 'reason' => $values['reason']]);

            return $balance;
        });

        return response()->json(['data' => $balance]);
    }

    public function carryOver(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);
        $values = $request->validate(['user_id' => 'required|uuid', 'source_year' => 'required|integer|min:2000|max:2099', 'target_year' => 'required|integer|gt:source_year|max:2100',
            'days' => 'required|numeric|gt:0|max:9999', 'reason' => 'required|string|min:3|max:4000']);
        $owner = $this->employee($request, $workflow, $values['user_id']);
        DB::transaction(function () use ($values, $owner, $request) {
            User::withoutGlobalScopes()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $source = LeaveBalance::where('user_id', $owner->id)->where('leave_type', 'annual')->where('year', $values['source_year'])->lockForUpdate()->first();
            abort_unless($source && $source->remaining_days >= $values['days'], 422, 'Reliquat insuffisant pour ce report.');
            $target = LeaveBalance::firstOrCreate(['user_id' => $owner->id, 'leave_type' => 'annual', 'year' => $values['target_year']]);
            $source->decrement('remaining_days', $values['days']);
            $source->refresh();
            $target->update(['initial_days' => $target->initial_days + $values['days'], 'remaining_days' => $target->remaining_days + $values['days']]);
            foreach ([[$source, -$values['days']], [$target, $values['days']]] as [$balance,$days]) {
                LeaveBalanceEntry::create(['leave_balance_id' => $balance->id, 'actor_id' => $request->user()->id,
                    'action' => 'carry_over', 'days' => $days, 'remaining_after' => $balance->remaining_days, 'reason' => $values['source_year'].' → '.$values['target_year'].' · '.$values['reason']]);
            }
        });

        return response()->json(['message' => 'Reliquat reporté et tracé.']);
    }

    public function policy(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);
        $values = $request->validate(['country_id' => 'required|uuid|exists:countries,id', 'permission_rules' => 'present|array|max:50',
            'permission_rules.*.code' => 'required|string|alpha_dash|max:100|distinct', 'permission_rules.*.label' => 'required|string|max:255',
            'permission_rules.*.days' => 'required|integer|min:1|max:365', 'permission_rules.*.requires_document' => 'sometimes|boolean',
            'absence_codes' => 'present|array', 'absence_codes.*' => 'nullable|string|max:100']);
        abort_unless($workflow->global($request->user()) || $request->user()->country_id === $values['country_id'], 403);
        $selected = CountryFilter::selectedValue();
        if ($selected && ! in_array($selected, ['group', 'default'])) {
            abort_unless(CountryFilter::resolveCountryId() === $values['country_id'], 422, 'Le pays du barème ne correspond pas au filtre actif.');
        }
        $policy = LeavePolicy::updateOrCreate(['country_id' => $values['country_id']], $values);
        activity()->causedBy($request->user())->performedOn($policy)->withProperties($values)->log('Mise à jour du barème des absences');

        return response()->json(['data' => $policy]);
    }

    public function getPolicy(Request $request, LeaveWorkflow $workflow)
    {
        $this->hr($request, $workflow);
        $request->validate(['country_id' => 'required|uuid|exists:countries,id']);
        abort_unless($workflow->global($request->user()) || $request->user()->country_id === $request->country_id, 403);

        return response()->json(['data' => LeavePolicy::where('country_id', $request->country_id)->first()]);
    }

    public function document(Request $request, string $id, string $document, LeaveWorkflow $workflow)
    {
        $leave = $workflow->visible($request->user())->where('activity_id', $id)->with(['steps', 'activity'])->firstOrFail();
        abort_unless($request->user()->id === $leave->user_id || $workflow->canDecide($request->user(), $leave) || $workflow->finalDecider($request->user()), 403);
        $file = LeaveDocument::where('leave_request_id', $leave->id)->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
