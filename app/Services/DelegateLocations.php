<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DelegateLocations
{
    public const DELEGATE_ROLES = ['delegue-medical', 'delegue_medical', 'delegue', 'delegate', 'Délégué', 'Délégué Médical'];

    public function forViewer(User $viewer, ?string $userId = null): Collection
    {
        $isGlobal = $viewer->can('scope:global') || $viewer->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        $query = User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->when($userId !== null, fn ($users) => $users->whereKey($userId))
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', self::DELEGATE_ROLES))
            ->when(!$isGlobal, fn ($users) => $users->whereIn('id', array_merge([$viewer->id], $viewer->teamMemberIds())))
            ->with([
                'country',
                'locations' => fn ($locations) => $this->validPointQuery($locations, 'lat', 'lng')
                    ->orderByDesc('recorded_at')->orderByDesc('created_at')->limit(1),
                'activities' => fn ($activities) => $activities->withoutGlobalScope(DynamicPerimeterScope::class)
                    ->visits()->select(['id', 'user_id', 'date_activite', 'heure_debut', 'heure_fin',
                        'start_time', 'end_time', 'gps_debut_lat', 'gps_debut_lng', 'gps_fin_lat', 'gps_fin_lng'])
                    ->where(fn ($gps) => $gps
                        ->where(fn ($start) => $this->validPointQuery($start, 'gps_debut_lat', 'gps_debut_lng'))
                        ->orWhere(fn ($end) => $this->validPointQuery($end, 'gps_fin_lat', 'gps_fin_lng')))
                    ->orderByRaw('COALESCE(end_time, start_time, date_activite) DESC')
                    ->orderByDesc('heure_fin')->orderByDesc('heure_debut')->limit(1),
            ])->orderBy('name');

        $users = $query->get();
        $todayVisits = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()
            ->whereIn('user_id', $users->modelKeys())->whereDate('date_activite', now()->toDateString())
            ->whereIn('statut', ['en_cours', 'planifiee', 'validee'])
            ->with(['contact', 'account'])->orderBy('heure_debut')->get()->groupBy('user_id');
        $now = now();

        return $users->map(function (User $user) use ($todayVisits, $now) {
            $candidates = [];
            $this->addPoint($candidates, $user->last_lat, $user->last_lng, $user->last_location_at, 'gps_live', $user->last_accuracy);
            foreach ($user->locations as $location) {
                $this->addPoint($candidates, $location->lat, $location->lng, $location->recorded_at, 'gps_live', $location->accuracy);
            }
            foreach ($user->activities as $activity) {
                $this->addPoint($candidates, $activity->gps_debut_lat, $activity->gps_debut_lng,
                    $this->visitTime($activity, false), 'check_in');
                $this->addPoint($candidates, $activity->gps_fin_lat, $activity->gps_fin_lng,
                    $this->visitTime($activity, true), 'check_out');
            }
            // Report edits and login dates are never interpreted as GPS timestamps.
            usort($candidates, fn ($a, $b) => ($b['at']?->timestamp ?? PHP_INT_MIN) <=> ($a['at']?->timestamp ?? PHP_INT_MIN));
            $position = $candidates[0] ?? null;
            $at = $position['at'] ?? null;
            $isLive = $position && $position['source'] === 'gps_live' && $at && $at->lte($now) && $at->gte($now->copy()->subMinutes(15));
            $visits = $todayVisits->get($user->id, collect());
            $currentVisit = $visits->firstWhere('statut', 'en_cours');
            $nextVisit = $currentVisit ?? $visits->first();
            $status = $currentVisit ? 'en_visite' : ($isLive ? 'disponible' : 'hors_ligne');
            $parts = preg_split('/\s+/u', trim($user->name), -1, PREG_SPLIT_NO_EMPTY);

            return [
                'id' => (string) $user->id, 'name' => $user->name,
                'initials' => mb_strtoupper(implode('', array_map(fn ($part) => mb_substr($part, 0, 1), array_slice($parts, 0, 2)))) ?: 'DM',
                'country_id' => $user->country_id, 'country_name' => $user->country?->name,
                'photo_url' => null, 'phone' => $user->phone ?? '',
                'status' => $status,
                'status_label' => $currentVisit ? 'En visite' : ($isLive ? 'Position récente' : ($position ? 'Dernière position connue' : 'Position non disponible')),
                'has_position' => $position !== null, 'is_live' => (bool) $isLive,
                'lat' => $position['lat'] ?? null, 'lng' => $position['lng'] ?? null,
                'position_source' => $position['source'] ?? null, 'accuracy' => $position['accuracy'] ?? null,
                'last_seen' => $at?->toISOString(), 'last_check_in' => $at?->format('H:i'),
                'location_name' => $nextVisit?->account?->nom ?? '',
                'hcp_name' => $nextVisit?->contact ? trim(($nextVisit->contact->titre ?: 'Dr.') . ' ' . $nextVisit->contact->prenom . ' ' . $nextVisit->contact->nom) : '',
                'hcp_spec' => '', 'sector' => '', 'time_left' => null,
            ];
        })->values();
    }

    private function validPointQuery($query, string $lat, string $lng)
    {
        return $query->whereNotNull($lat)->whereNotNull($lng)->whereBetween($lat, [-90, 90])->whereBetween($lng, [-180, 180]);
    }

    private function addPoint(array &$points, $lat, $lng, $at, string $source, $accuracy = null): void
    {
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) return;
        $points[] = ['lat' => (float) $lat, 'lng' => (float) $lng, 'at' => $at ? Carbon::parse($at) : null,
            'source' => $source, 'accuracy' => is_numeric($accuracy) ? (float) $accuracy : null];
    }

    private function visitTime(Activity $activity, bool $end): ?Carbon
    {
        $recorded = $end ? $activity->end_time : $activity->start_time;
        if ($recorded) return Carbon::parse($recorded);
        $time = $end ? $activity->heure_fin : $activity->heure_debut;
        if (!$activity->date_activite || !$time) return null;
        return Carbon::parse(Carbon::parse($activity->date_activite)->toDateString() . ' ' . $time);
    }
}
