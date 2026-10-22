<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OutsideWeeklyPlanning
{
    public static function limit(): int
    {
        return min(5, max(1, (int) config('planning.outside_weekly_limit', 5)));
    }

    /** Called inside the activity write transaction; serializes requests for a delegate. */
    public function checkCapacity(string $userId, string $date, ?string $excludeId = null): void
    {
        User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->firstOrFail();
        $monday = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $count = Activity::withoutGlobalScopes()->withTrashed()
            ->where('user_id', $userId)
            ->where('outside_weekly_plan', true)
            ->whereBetween('date_activite', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->count();

        if ($count >= self::limit()) {
            throw ValidationException::withMessages([
                'outside_weekly_plan' => 'La marge de ' . self::limit() . ' visites hors planning hebdomadaire est atteinte pour cette semaine. Soumettez les autres visites via votre planning habituel.',
            ]);
        }
    }
}
