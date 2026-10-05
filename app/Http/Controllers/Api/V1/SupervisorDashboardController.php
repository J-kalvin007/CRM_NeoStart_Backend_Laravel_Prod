<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\SampleRequest;
use App\Models\TourPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord & planning du superviseur.
 * Toutes les données sont restreintes aux délégués qu'il encadre (User::teamMemberIds()).
 */
class SupervisorDashboardController extends Controller
{
    public const COMPLETED = ['realisee', 'effectuee', 'validee'];
    private const LEAVE_TYPES = ['conge', 'absence', 'indisponibilite', 'leave', 'Congé Annuel', 'Congé Maladie'];

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $team = $user->teamMembers()->with('country')->get();
        $teamIds = $team->pluck('id')->all();
        $count = $team->count();

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $completed = $this->visitQuery($teamIds)
            ->whereBetween('date_activite', [$start, $end])
            ->whereIn('statut', self::COMPLETED)
            ->count();
        $target = $this->weeklyTarget() * 4 * $count;
        $progress = $target > 0 ? round($completed / $target, 2) : 0;

        $coverage = $this->coverageRate($teamIds, $start, $end);
        $pending = $this->pendingValidationsCount($teamIds);

        $connectedToday = $team->filter(fn ($u) => $u->last_login_at && Carbon::parse($u->last_login_at)->isToday())->count();
        $connectedPct = $count > 0 ? (int) round($connectedToday / $count * 100) : 0;

        $liveActivities = $this->visitQuery($teamIds)
            ->with(['delegue.country', 'contact', 'account'])
            ->whereDate('date_activite', now()->toDateString())
            ->orderBy('heure_debut')
            ->get()
            ->map(fn ($act) => [
                'name' => $act->delegue?->name ?? 'Délégué',
                'user_id' => (string) $act->user_id,
                'time' => $act->heure_debut ? substr($act->heure_debut, 0, 5) : null,
                'sector' => $this->delegateSector($act->delegue),
                'statusBadgeText' => $this->statusLabel($act->statut),
                'subText' => trim($this->contactName($act) . ($act->account ? " ({$act->account->nom})" : '')),
            ])->values();

        $validations = Expense::withoutGlobalScopes()
            ->with('user')
            ->whereIn('user_id', $teamIds)
            ->where('status', 'submitted')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($e) => [
                'id' => (string) $e->id,
                'title' => $e->title ?: ucfirst((string) $e->type),
                'submittedBy' => 'Soumis par ' . ($e->user?->name ?? 'Délégué') . ' • ' . $e->created_at?->format('d/m/Y'),
                'amountLocal' => number_format((float) $e->amount, 0, ',', ' ') . ' ' . ($e->currency ?: 'FCFA'),
                'receipt' => $e->receipt_file_path ? 'Justificatif joint' : 'Sans justificatif',
            ])->values();

        return response()->json([
            'supervision' => [
                'region' => $this->delegateSector($user) ?: 'SUPERVISION',
                'team' => 'Équipe de ' . $user->name,
                'activeDelegates' => $count,
                'connectedPercentage' => $connectedPct,
            ],
            'kpis' => [
                [
                    'title' => 'VISITES MTD',
                    'value' => (string) $completed,
                    'subtitle' => "/{$target}",
                    'progress' => min(1, $progress),
                    'bottomLabel' => '↗ ' . (int) round($progress * 100) . "% de l'objectif",
                ],
                [
                    'title' => 'COUVERTURE',
                    'value' => $coverage . '%',
                    'progress' => $coverage / 100,
                    'bottomLabel' => 'Prescripteurs visités ce mois',
                ],
                [
                    'title' => 'VALIDATIONS EN COURS',
                    'value' => (string) $pending,
                    'subtitleText' => 'À valider',
                    'bottomBadge' => $pending > 0 ? '⏳ Action requise' : '✅ À jour',
                ],
                [
                    'title' => 'DÉLÉGUÉS ACTIFS',
                    'value' => (string) $count,
                    'subtitleText' => 'Dans mon équipe',
                    'bottomBadge' => "{$connectedPct}% connectés aujourd'hui",
                ],
            ],
            'liveActivities' => $liveActivities,
            'validations' => $validations,
        ]);
    }

    public function teamPlanning(Request $request)
    {
        $user = auth()->user();
        $sectorFilter = $request->query('sector', 'Tous les secteurs');
        $delegateFilter = $request->query('delegate', 'Tous');
        $delegateIdFilter = $request->query('delegate_id');
        $temporality = $request->query('temporality', 'Jour');

        try {
            $date = Carbon::parse($request->query('date', now()->toDateString()));
        } catch (\Throwable $e) {
            $date = now();
        }

        [$start, $end, $workingDays] = match ($temporality) {
            'Semaine' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $this->daysPerWeek()],
            'Mois' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth(), $this->daysPerWeek() * 4],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay(), 1],
        };
        $targetPerDelegate = $this->visitsPerDay() * $workingDays;

        $team = $user->teamMembers()->with('country')->orderBy('name')->get();
        if ($team->isEmpty()) {
            $team = collect([$user]);
        }
        $teamIds = $team->pluck('id')->all();
        if (!in_array($user->id, $teamIds)) {
            $teamIds[] = $user->id;
        }

        // Planifiées / réalisées par délégué sur la période affichée
        $stats = $this->visitQuery($teamIds)
            ->whereBetween('date_activite', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('user_id, COUNT(*) as planned, SUM(CASE WHEN statut IN (?, ?, ?) THEN 1 ELSE 0 END) as completed', self::COMPLETED)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $monthStart = $date->copy()->startOfMonth()->toDateString();
        $monthEnd = $date->copy()->endOfMonth()->toDateString();
        $teamCoverage = $this->coverageRate($teamIds, $monthStart, $monthEnd);

        $delegates = [[
            'id' => 'del-all',
            'name' => 'Tous',
            'avatar' => 'ALL',
            'role' => 'Équipe au complet',
            'sector' => 'Tous les secteurs',
            'coverage' => $teamCoverage . '%',
            'status' => '',
            'planned_visits' => (int) $stats->sum('planned') . '/' . ($targetPerDelegate * count($teamIds)) . ' prévues',
        ]];

        foreach ($team as $member) {
            $planned = (int) ($stats[$member->id]->planned ?? 0);
            $sector = $this->delegateSector($member);
            $delegates[] = [
                'id' => (string) $member->id,
                'name' => $member->name,
                'avatar' => $this->initials($member->name),
                'role' => 'Délégué Médical' . ($sector ? " • {$sector}" : ''),
                'sector' => $sector,
                'coverage' => $this->coverageRate([$member->id], $monthStart, $monthEnd) . '%',
                'status' => $this->tourPlanStatus($member->id, $date),
                'planned_visits' => "{$planned}/{$targetPerDelegate} prévues",
            ];
        }

        $sectors = array_values(array_unique(array_filter(array_column(array_slice($delegates, 1), 'sector'))));
        array_unshift($sectors, 'Tous les secteurs');

        // Délégués retenus par les filtres (toujours à l'intérieur de l'équipe)
        $filteredIds = $team->filter(function ($m) use ($delegateFilter, $delegateIdFilter, $sectorFilter) {
            if ($delegateIdFilter) {
                return $m->id === $delegateIdFilter;
            }
            if ($delegateFilter && $delegateFilter !== 'Tous' && $m->name !== $delegateFilter) {
                return false;
            }
            if ($sectorFilter && $sectorFilter !== 'Tous les secteurs' && $this->delegateSector($m) !== $sectorFilter) {
                return false;
            }
            return true;
        })->pluck('id')->all();

        \App\Models\Activity::markMissedPastActivities();

        $schedules = $this->visitQuery($filteredIds)
            ->with(['delegue.country', 'contact', 'account', 'products', 'duoUser', 'duoActivityType'])
            ->whereBetween('date_activite', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date_activite')
            ->orderBy('heure_debut')
            ->get()
            ->map(fn ($act) => $this->formatSchedule($act, $user))
            ->values();

        return response()->json([
            'status' => 'success',
            'date' => $date->toDateString(),
            'temporality' => $temporality,
            'selected_sector' => $sectorFilter,
            'selected_delegate' => $delegateFilter,
            'sectors' => $sectors,
            'delegates' => $delegates,
            'schedules' => $schedules,
            'summary' => [
                'total_delegates' => count($teamIds),
                'validated_plannings' => collect($delegates)->where('status', 'Validée')->count(),
                'pending_plannings' => collect($delegates)->where('status', 'Soumis')->count(),
                'planned_visits' => (int) $stats->sum('planned'),
                'completed_visits' => (int) $stats->sum('completed'),
                'target_visits' => $targetPerDelegate * count($teamIds),
                'global_coverage' => $teamCoverage . '%',
            ],
        ]);
    }

    /* ─── Helpers ──────────────────────────────────────────────── */

    private function formatSchedule(Activity $act, User $supervisor): array
    {
        $actType = !empty($act->activity_type) ? ucfirst($act->activity_type) : 'Visite médicale';
        if (strtolower($actType) === 'visite') {
            $actType = 'Visite médicale';
        }
        $statut = strtolower((string) $act->statut);
        $isClosed = in_array($statut, ['realisee', 'effectuee', 'terminee', 'annulee', 'manquee']);
        $isPast = $act->date_activite && $act->date_activite->lt(now()->startOfDay());
        $contactName = $this->contactName($act);
        $address = $act->account
            ? trim(implode(', ', array_filter([$act->account->adresse, $act->account->zone_quartier, $act->account->ville])))
            : '';

        return [
            'id' => (string) $act->id,
            'delegate' => $act->delegue?->name ?? 'Délégué',
            'delegate_id' => (string) $act->user_id,
            'dateActivite' => $act->date_activite?->toDateString(),
            'time' => $act->heure_debut ? substr($act->heure_debut, 0, 5) : '--:--',
            'heure_fin' => $act->heure_fin ? substr($act->heure_fin, 0, 5) : null,
            'duree_minutes' => $act->duree_minutes,
            'title' => "{$actType} — {$contactName}",
            'specialty' => trim(($act->account?->nom ?? '') . ($act->notes ? ' • ' . $act->notes : ''), ' •'),
            'type' => $actType,
            'status' => $this->statusLabel($act->statut),
            'isDuo' => $act->hasActiveDuo(),
            'sector' => $this->delegateSector($act->delegue),
            'color' => in_array($statut, self::COMPLETED) ? '#00816E' : ($statut === 'manquee' ? '#D32F2F' : '#0050CB'),
            'contact_name' => $act->contact ? $contactName : null,
            'account_name' => $act->account?->nom,
            'address' => $address ?: null,
            'notes' => $act->notes,
            'motif' => $act->motif,
            'products' => $act->products->map(fn ($p) => $p->nom)->filter()->values(),
            'accompanying_user' => $act->hasActiveDuo() ? $act->duoUser?->name : null,
            'duo_type' => $act->hasActiveDuo() ? $act->duoActivityType?->code : null,
            // pending | accepted | rejected : réponse du délégué à l'accompagnement
            'duo_delegue_status' => $act->is_duo ? $act->duo_delegue_status : null,
            'duo_delegue_rejection_reason' => $act->duo_delegue_rejection_reason,
            'can_duo' => !$act->hasActiveDuo() && $act->user_id !== $supervisor->id && !$isClosed && !$isPast,
            // Le superviseur qui anime ce DUO peut le modifier ou l'annuler tant qu'il n'est pas passé
            'can_manage_duo' => $act->hasActiveDuo() && $act->duo_user_id === $supervisor->id && !$isPast,
            // Visite modifiable / reprogrammable / supprimable par le superviseur
            'can_edit' => !$act->is_locked && !$isClosed,
        ];
    }

    /** Visites (hors congés/absences) des utilisateurs donnés ; la restriction d'équipe se fait par les IDs. */
    public function visitQuery(array $userIds)
    {
        return Activity::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('user_id', $userIds)
            ->whereNotIn('activity_type', self::LEAVE_TYPES);
    }

    /** % des prescripteurs du portefeuille visités (visite réalisée) sur la période. */
    public function coverageRate(array $userIds, string $start, string $end): int
    {
        if (empty($userIds)) {
            return 0;
        }
        $portfolio = Contact::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('user_id', $userIds)->count();
        if ($portfolio === 0) {
            return 0;
        }
        $visited = $this->visitQuery($userIds)
            ->whereBetween('date_activite', [$start, $end])
            ->whereIn('statut', self::COMPLETED)
            ->whereNotNull('contact_id')
            ->distinct()
            ->count('contact_id');

        return (int) min(100, round($visited / $portfolio * 100));
    }

    private function pendingValidationsCount(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return Expense::withoutGlobalScopes()->whereIn('user_id', $userIds)->where('status', 'submitted')->count()
            + TourPlan::withoutGlobalScopes()->whereIn('user_id', $userIds)->where('statut', 'soumis')->count()
            + SampleRequest::whereIn('user_id', $userIds)->where('status', 'pending')->count()
            + Activity::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('user_id', $userIds)
                ->whereIn('activity_type', self::LEAVE_TYPES)->where('statut', 'en_attente')->count()
            + Activity::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('user_id', $userIds)
                ->where('report_status', 'submitted')->count()
            + Contact::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('user_id', $userIds)
                ->where(fn ($q) => $q->whereIn('statut_validation', ['en_attente', 'pending'])
                    ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']))->count()
            + \App\Models\Account::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('user_id', $userIds)
                ->where(fn ($q) => $q->whereIn('statut_validation', ['en_attente', 'pending'])
                    ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']))->count();
    }

    private function tourPlanStatus(string $userId, Carbon $date): string
    {
        $plan = TourPlan::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereDate('date_debut', '<=', $date->toDateString())
            ->whereDate('date_fin', '>=', $date->toDateString())
            ->latest()
            ->first();

        return match (strtolower((string) $plan?->statut)) {
            'valide', 'validee' => 'Validée',
            'soumis' => 'Soumis',
            'rejete', 'refuse', 'refusee' => 'Refusée',
            '' => 'Non soumis',
            default => ucfirst($plan->statut),
        };
    }

    /** Secteur = Pays de l'utilisateur. */
    public function delegateSector(?User $u): string
    {
        if (!$u) {
            return '';
        }
        $country = $u->relationLoaded('country') ? $u->country : $u->country()->first();

        return (string) ($country?->name ?? '');
    }

    private function contactName(Activity $act): string
    {
        if (!$act->contact) {
            return 'Prescripteur non renseigné';
        }

        return trim(($act->contact->titre ?: 'Dr.') . ' ' . $act->contact->prenom . ' ' . $act->contact->nom);
    }

    private function statusLabel(?string $statut): string
    {
        return match (strtolower((string) $statut)) {
            'realisee', 'effectuee' => 'Réalisée',
            'validee' => 'Validée',
            'planifiee', '' => 'Planifiée',
            'en_cours' => 'En cours',
            'en_attente' => 'En attente',
            'manquee' => 'Manquée',
            'annulee' => 'Annulée',
            'refusee' => 'Refusée',
            default => ucfirst(str_replace('_', ' ', $statut)),
        };
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim(preg_replace('/\(.*?\)/', '', $name)))));

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2)))) ?: 'DM';
    }

    private function visitsPerDay(): int
    {
        return (int) (DB::table('settings')->where('key', 'visits_per_day')->value('value') ?: 12);
    }

    private function daysPerWeek(): int
    {
        return (int) (DB::table('settings')->where('key', 'working_days_per_week')->value('value') ?: 5);
    }

    public function weeklyTarget(): int
    {
        return $this->visitsPerDay() * $this->daysPerWeek();
    }
}
