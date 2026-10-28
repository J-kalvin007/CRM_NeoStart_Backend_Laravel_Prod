<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VisitExecution
{
    public const COMPLETED = ['realisee', 'effectuee', 'terminee', 'cloturee', 'soumis_definitif', 'completed', 'done'];

    public static function describe(Activity $visit, Carbon $now): array
    {
        $status = str_replace(' ', '_', strtolower(Str::ascii(trim((string)$visit->statut))));
        $excluded = match (true) {
            in_array($status, ['brouillon', 'draft']) => 'Brouillon',
            in_array($status, ['annulee', 'annule', 'cancelled', 'canceled']) => 'Annulée',
            in_array($status, ['refusee', 'rejetee', 'refuse', 'rejected']) => 'Refusée',
            default => null,
        };
        $date = $visit->date_activite?->toDateString();
        $plannedStart = $date && $visit->heure_debut ? Carbon::parse($date.' '.$visit->heure_debut) : null;
        $plannedEnd = $date && $visit->heure_fin
            ? Carbon::parse(($visit->date_fin?->toDateString() ?? $date).' '.$visit->heure_fin) : null;
        $deadline = $plannedEnd ?? $plannedStart;
        $completed = in_array($status, self::COMPLETED, true);
        $state = match (true) {
            $excluded !== null => 'excluded', $completed => 'completed',
            in_array($status, ['en_cours', 'in_progress', 'ongoing']) => 'in_progress',
            in_array($status, ['manquee', 'missed']) => 'missed',
            $deadline !== null && $deadline->lt($now) => 'overdue',
            $deadline === null && $date !== null && $date < $now->toDateString() => 'overdue',
            $date !== null && ($date > $now->toDateString() || ($plannedStart !== null && $plannedStart->gt($now))) => 'upcoming',
            default => 'pending',
        };
        return [
            'id' => $visit->id, 'user_id' => $visit->user_id, 'planned_date' => $date,
            'planned_start' => $plannedStart?->toISOString(), 'planned_end' => $plannedEnd?->toISOString(),
            'actual_start' => $visit->start_time?->toISOString(), 'actual_end' => $visit->end_time?->toISOString(),
            'start_delay_minutes' => $plannedStart && $visit->start_time ? (int)$plannedStart->diffInMinutes($visit->start_time, false) : null,
            'started_on_time' => $plannedStart && $visit->start_time ? $visit->start_time->lte($plannedStart) : null,
            'state' => $state, 'counted' => $excluded === null,
            'state_label' => $excluded ?? match ($state) {
                'completed' => 'Réalisée', 'in_progress' => 'En cours', 'missed' => 'Manquée',
                'overdue' => 'En retard, non réalisée', 'upcoming' => 'À venir', default => 'À réaliser',
            },
            'visit_status' => $visit->statut,
        ];
    }

    public static function summarize($rows): array
    {
        $counted = collect($rows)->where('counted', true);
        $completed = $counted->where('state', 'completed')->count();
        $planned = $counted->count();
        return [
            'planned_visits' => $planned, 'completed_visits' => $completed,
            'remaining_visits' => $planned - $completed,
            'execution_rate' => $planned ? round($completed / $planned * 100, 1) : null,
            'in_progress_visits' => $counted->where('state', 'in_progress')->count(),
            'missed_visits' => $counted->where('state', 'missed')->count(),
            'overdue_visits' => $counted->where('state', 'overdue')->count(),
            'upcoming_visits' => $counted->where('state', 'upcoming')->count(),
            'pending_visits' => $counted->where('state', 'pending')->count(),
            'excluded_visits' => collect($rows)->where('counted', false)->count(),
        ];
    }
}
