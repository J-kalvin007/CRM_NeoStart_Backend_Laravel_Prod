<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\User;
use App\Models\UserLocation;
use App\Services\VisitExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DelegateItineraryController extends Controller
{
    public function __invoke(Request $request, string $id)
    {
        $request->validate(['date' => 'sometimes|date_format:Y-m-d']);
        $viewer = $request->user();
        abort_unless($viewer->canEditRecordOf($id), 403);
        $person = User::withoutGlobalScope(DynamicPerimeterScope::class)->findOrFail($id);
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        $points = UserLocation::where('user_id', $id)->whereBetween('recorded_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->whereBetween('lat', [-90, 90])->whereBetween('lng', [-180, 180])
            ->where(fn ($q) => $q->where('lat', '!=', 0)->orWhere('lng', '!=', 0))
            ->orderBy('recorded_at')->get(['lat', 'lng', 'accuracy', 'speed', 'recorded_at']);
        $distance = null;
        if ($points->count() >= 2) {
            $distance = 0;
            for ($i = 1; $i < $points->count(); $i++) {
                $a = $points[$i - 1];
                $b = $points[$i];
                $dLat = deg2rad($b->lat - $a->lat);
                $dLng = deg2rad($b->lng - $a->lng);
                $h = sin($dLat / 2) ** 2 + cos(deg2rad($a->lat)) * cos(deg2rad($b->lat)) * sin($dLng / 2) ** 2;
                $distance += 6371 * 2 * atan2(sqrt(max(0, $h)), sqrt(max(0, 1 - $h)));
            }
            $distance = round($distance, 2);
        }
        $visits = Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()->where('user_id', $id)
            ->whereDate('date_activite', $date)->whereNotIn('statut', ['brouillon', 'draft'])
            ->with(['contact.account', 'contacts', 'account'])->orderBy('heure_debut')->get()->map(fn ($visit) => array_merge(VisitExecution::describe($visit, now()), [
                'target' => collect([$visit->contact])->merge($visit->contacts)->filter()->map(fn ($contact) => trim($contact->prenom.' '.$contact->nom))->filter()->unique()->join(', ') ?: ($visit->account?->nom ?? 'Cible non renseignée'),
                'facility' => $visit->account?->nom ?? $visit->contact?->account?->nom,
                'report_submitted' => $visit->hasPublishedReport(),
                'duration_minutes' => $visit->start_time && $visit->end_time && $visit->end_time->gte($visit->start_time)
                    ? round($visit->start_time->diffInMinutes($visit->end_time), 1) : null,
                'lat' => $visit->gps_debut_lat, 'lng' => $visit->gps_debut_lng,
            ]));

        return response()->json(['delegate' => ['id' => $person->id, 'name' => $person->name], 'date' => $date->toDateString(),
            'points' => $points, 'visits' => $visits, 'summary' => array_merge(VisitExecution::summarize($visits), [
                'distance_km_estimated' => $distance, 'gps_points' => $points->count(),
                'first_seen' => $points->first()?->recorded_at?->toISOString(), 'last_seen' => $points->last()?->recorded_at?->toISOString(),
                'distance_method' => 'Somme des segments entre les points GPS reçus ; trajet routier et temps de conduite non déduits.',
            ])]);
    }
}
