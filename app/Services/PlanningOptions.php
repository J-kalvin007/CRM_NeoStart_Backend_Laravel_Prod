<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlanningOptions
{
    public const CALENDAR_CODES = ['rcp', 'reunion', 'conge', 'permission'];

    public static function kind(?string $code): string
    {
        $code = strtolower(Str::ascii(trim((string) $code)));
        return in_array($code, self::CALENDAR_CODES, true) ? $code : 'visite';
    }

    public function forUser(string $userId, string $date): array
    {
        $monday = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $limit = OutsideWeeklyPlanning::limit();
        $used = Activity::withoutGlobalScopes()->withTrashed()
            ->where('user_id', $userId)->where('outside_weekly_plan', true)
            ->whereBetween('date_activite', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])->count();
        $types = ActivityType::where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($type) => self::kind($type->code) !== 'visite')
            ->map(fn ($type) => ['id' => $type->id, 'code' => self::kind($type->code), 'label' => $type->name])
            ->values()->all();

        return [
            'calendar_types' => $types,
            'outside_weekly_limit' => $limit, 'outside_weekly_used' => $used,
            'outside_weekly_remaining' => max(0, $limit - $used),
            'week_start' => $monday->toDateString(), 'week_end' => $monday->copy()->addDays(6)->toDateString(),
            'visit_reminder_minutes' => max(1, (int) config('planning.visit_reminder_minutes', 20)),
        ];
    }
}
