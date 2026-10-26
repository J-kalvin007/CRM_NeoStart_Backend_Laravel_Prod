<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlanningOptions
{
    public const CALENDAR_CODES = ['reunion', 'conge', 'permission'];
    public const STORED_CALENDAR_CODES = ['reunion', 'conge', 'permission', 'rcp'];

    public static function kind(?string $code): string
    {
        $code = strtolower(Str::ascii(trim((string) $code)));
        $code = match ($code) {
            'conge annuel', 'conge maladie', 'absence', 'indisponibilite', 'leave', 'arret maladie', 'conge paye', 'rtt', 'formation', 'evenement familial' => 'conge',
            default => $code,
        };
        return in_array($code, self::STORED_CALENDAR_CODES, true) ? $code : 'visite';
    }

    public function forUser(string $userId, string $date): array
    {
        $monday = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $limit = OutsideWeeklyPlanning::limit();
        $used = Activity::withoutGlobalScopes()->withTrashed()
            ->where('user_id', $userId)->where('outside_weekly_plan', true)
            ->whereBetween('date_activite', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])->count();
        $types = ActivityType::where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($type) => in_array(self::kind($type->code), self::CALENDAR_CODES, true))
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
