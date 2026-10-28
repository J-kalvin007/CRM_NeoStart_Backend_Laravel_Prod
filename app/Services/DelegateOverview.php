<?php

namespace App\Services;

use App\Http\Resources\ObjectifResource;
use App\Models\{Activity, Objectif, TourPlan, User};
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Http\Request;

class DelegateOverview
{
    private function people(Request $request)
    {
        $viewer = $request->user();
        $global = $viewer->can('scope:global') || $viewer->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        return User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', DelegateLocations::DELEGATE_ROLES))
            ->when(!$global, fn ($q) => $q->whereIn('id', array_merge([$viewer->id], $viewer->teamMemberIds())))
            ->with(['roles', 'country', 'manager:id,name'])->orderBy('name');
    }

    public function index(Request $request): array
    {
        $people = $this->people($request)->get();
        $overview = app(PerformanceOverview::class)->forRequest($request, $people->modelKeys());
        $profiles = $people->keyBy('id');
        $overview['data'] = $overview['data']->map(fn ($row) => array_merge($row, [
            'email' => $profiles[$row['id']]->email, 'phone' => $profiles[$row['id']]->phone,
            'manager_name' => $profiles[$row['id']]->manager?->name,
        ]));
        unset($overview['planning']);
        return $overview;
    }

    public function show(Request $request, string $id): array
    {
        // Country selection and hierarchy both apply before a profile can be resolved.
        $person = $this->people($request)->whereKey($id)->firstOrFail();
        $filters = $request->only(['period', 'start_date', 'end_date']);
        $scoped = $request->duplicate(array_merge($filters, ['user_id' => $person->id]));
        $scoped->setUserResolver(fn () => $request->user());
        $overview = app(PerformanceOverview::class)->forRequest($scoped, [$person->id]);
        $start = $overview['meta']['start_date']; $end = $overview['meta']['end_date'];
        $query = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->where('user_id', $person->id)
            ->whereBetween('date_activite', [$start, $end]);
        $visits = (clone $query)->visits()->with(['contact.account', 'contacts', 'account', 'activityType'])->orderByDesc('date_activite')->orderBy('heure_debut')->get();
        $reports = $visits->filter(fn ($visit) => $visit->hasPublishedReport());
        $reports->load(['products', 'sampleMovements.product', 'objections']);
        $base = function (Activity $visit) use ($person) {
            return [
                'id' => $visit->id, 'user_id' => $person->id, 'user_name' => $person->name, 'country_name' => $person->country?->name,
                'date_activite' => $visit->date_activite?->toDateString(), 'heure_debut' => $visit->heure_debut, 'heure_fin' => $visit->heure_fin,
                'start_time' => $visit->start_time?->toISOString(), 'end_time' => $visit->end_time?->toISOString(),
                'statut' => $visit->statut, 'activity_type' => $visit->activity_type_name,
                'doctorName' => collect([$visit->contact])->merge($visit->contacts)->filter()->map(fn ($contact) => trim($contact->prenom.' '.$contact->nom))->filter()->unique()->join(', ') ?: ($visit->account?->nom ?? ''),
                'account' => ($visit->account ?? $visit->contact?->account) ? ['id' => ($visit->account ?? $visit->contact->account)->id, 'nom' => ($visit->account ?? $visit->contact->account)->nom] : null,
                'specialty' => $visit->contact?->specialite,
                'report_status' => $visit->hasPublishedReport() ? 'submitted' : null,
            ];
        };
        $objectives = Objectif::withoutGlobalScope(DynamicPerimeterScope::class)->where('user_id', $person->id)
            ->whereDate('date_debut', '<=', $end)->whereDate('date_fin', '>=', $start)
            ->with(['user.country', 'user.roles', 'country', 'type', 'product', 'indicator'])->get();
        $plans = TourPlan::withoutGlobalScope(DynamicPerimeterScope::class)->where('user_id', $person->id)
            ->whereDate('date_debut', '<=', $end)->whereDate('date_fin', '>=', $start)->orderByDesc('date_debut')->get()
            ->map(fn ($plan) => ['id' => $plan->id, 'nom' => $plan->nom, 'date_debut' => $plan->date_debut?->toDateString(),
                'date_fin' => $plan->date_fin?->toDateString(), 'statut' => $plan->statut,
                'description' => $plan->description, 'commentaire_validation' => $plan->commentaire_validation]);
        $calendar = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->where('user_id', $person->id)->calendar()
            ->whereDate('date_activite', '<=', $end)->whereRaw('COALESCE(date_fin, date_activite) >= ?', [$start])
            ->with('activityType')->orderBy('date_activite')->get()->map(fn ($activity) => [
                'id' => $activity->id, 'user_name' => $person->name, 'type_label' => $activity->activity_type_name,
                'type' => $activity->planning_kind, 'start_date' => $activity->date_activite?->toDateString(),
                'end_date' => $activity->date_fin?->toDateString() ?? $activity->date_activite?->toDateString(),
                'heure_debut' => $activity->heure_debut, 'heure_fin' => $activity->heure_fin, 'status' => $activity->statut,
            ]);
        $contacts = $person->contacts()->withoutGlobalScope(DynamicPerimeterScope::class)->with('speciality')->orderBy('nom')->get()
            ->map(fn ($contact) => ['id' => $contact->id, 'nom' => $contact->nom, 'prenom' => $contact->prenom,
                'speciality' => $contact->speciality ? ['name' => $contact->speciality->name] : null,
                'telephone' => $contact->telephone, 'email' => $contact->email, 'ville' => $contact->ville]);
        $accounts = $person->accounts()->withoutGlobalScope(DynamicPerimeterScope::class)->orderBy('nom')->get()
            ->map(fn ($account) => ['id' => $account->id, 'nom' => $account->nom, 'ville' => $account->ville,
                'adresse' => $account->adresse, 'telephone' => $account->telephone, 'email' => $account->email]);
        return ['profile' => [
                'id' => $person->id, 'name' => $person->name, 'email' => $person->email, 'phone' => $person->phone,
                'country_id' => $person->country_id, 'country_name' => $person->country?->name,
                'roles' => $person->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
                'manager_name' => $person->manager?->name, 'created_at' => $person->created_at?->toISOString(),
            ],
            'performance' => $overview['data']->first(), 'planning' => $overview['planning'], 'meta' => $overview['meta'],
            'position' => app(DelegateLocations::class)->forViewer($request->user(), $person->id)->first(),
            'visits' => $visits->map($base)->values(),
            'reports' => $reports->map(fn ($visit) => array_merge($base($visit), [
                'report_status' => 'submitted', 'report_content' => $visit->report_content, 'duree_minutes' => $visit->duree_minutes,
                'products' => $visit->products->map(fn ($product) => ['nom' => $product->nom, 'quantity' => $product->pivot->quantity]),
                'samples' => $visit->sampleMovements->map(fn ($sample) => ['product_name' => $sample->product?->nom, 'quantity' => $sample->quantity]),
                'objections' => $visit->objections->map(fn ($objection) => ['name' => $objection->name ?? $objection->nom, 'commentaire' => $objection->pivot->notes]),
            ]))->values(),
            'objectives' => ObjectifResource::collection($objectives)->resolve($request), 'weekly_plans' => $plans,
            'calendar' => $calendar, 'contacts' => $contacts, 'accounts' => $accounts,
        ];
    }
}
