<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ComplianceFinding;
use App\Models\SampleMovement;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\Setting;
use App\Models\User;

class ComplianceReview
{
    public function userIds(User $viewer): array
    {
        return User::withoutGlobalScope(DynamicPerimeterScope::class)
            ->when(! ($viewer->can('scope:global') || $viewer->hasAnyRole(['superadmin', 'super-admin', 'admin'])), fn ($q) => $q->whereIn('id', array_merge([$viewer->id], $viewer->teamMemberIds())))
            ->pluck('id')->all();
    }

    public function rules(): array
    {
        $values = Setting::whereIn('key', ['compliance_short_visit_minutes', 'compliance_gps_tolerance_meters'])->pluck('value', 'key');

        return [
            ['code' => 'DOUBLON_SUSPECT', 'label' => 'Même collaborateur, cible, date et horaire prévus', 'enabled' => true, 'threshold' => null],
            ['code' => 'HORAIRES_INCOHERENTS', 'label' => 'Fin réelle antérieure au début réel', 'enabled' => true, 'threshold' => null],
            ['code' => 'ÉCHANTILLON_ANOMALIE', 'label' => 'Solde de lot négatif dans le registre', 'enabled' => true, 'threshold' => null],
            ['code' => 'DURÉE_COURTE', 'label' => 'Durée réelle inférieure au seuil configuré (minutes)', 'enabled' => ! empty($values['compliance_short_visit_minutes']), 'threshold' => isset($values['compliance_short_visit_minutes']) ? (float) $values['compliance_short_visit_minutes'] : null],
            ['code' => 'ÉCART_GPS', 'label' => 'Distance du pointage au praticien supérieure au seuil configuré (mètres)', 'enabled' => ! empty($values['compliance_gps_tolerance_meters']), 'threshold' => isset($values['compliance_gps_tolerance_meters']) ? (float) $values['compliance_gps_tolerance_meters'] : null],
        ];
    }

    public function detect(User $viewer): void
    {
        $ids = $this->userIds($viewer);
        $rules = collect($this->rules())->keyBy('code');
        $seen = [];
        Activity::withoutGlobalScope(DynamicPerimeterScope::class)->visits()->whereIn('user_id', $ids)
            ->whereNotIn('statut', ['brouillon', 'draft', 'annulee', 'annule', 'cancelled', 'refusee', 'rejetee'])
            ->with(['contact', 'contacts:id'])->chunkById(300, function ($visits) use (&$seen, $rules) {
                foreach ($visits as $visit) {
                    $targets = $visit->contacts->pluck('id')->push($visit->contact_id)->filter()->unique()->sort()->join(',');
                    $targets = $targets ?: ($visit->account_id ? 'account:'.$visit->account_id : '');
                    if ($targets && $visit->heure_debut) {
                        $key = implode('|', [$visit->user_id, $visit->date_activite?->toDateString(), $visit->heure_debut, $targets]);
                        if (isset($seen[$key])) {
                            $this->record($visit->user_id, $visit->id, 'DOUBLON_SUSPECT', 'MODERE', 'Deux visites ont la même cible et le même créneau.', ['activity_ids' => [$seen[$key], $visit->id], 'date' => $visit->date_activite?->toDateString(), 'time' => $visit->heure_debut]);
                        } else {
                            $seen[$key] = $visit->id;
                        }
                    }
                    if ($visit->start_time && $visit->end_time) {
                        $evidence = ['start' => $visit->start_time->toISOString(), 'end' => $visit->end_time->toISOString()];
                        if ($visit->end_time->lt($visit->start_time)) {
                            $this->record($visit->user_id, $visit->id, 'HORAIRES_INCOHERENTS', 'CRITIQUE', 'La fin de visite précède son début.', $evidence);
                        } elseif ($rules['DURÉE_COURTE']['enabled'] && $visit->start_time->diffInMinutes($visit->end_time) < $rules['DURÉE_COURTE']['threshold']) {
                            $this->record($visit->user_id, $visit->id, 'DURÉE_COURTE', 'MODERE', 'La durée est inférieure au seuil configuré.', $evidence + ['threshold_minutes' => $rules['DURÉE_COURTE']['threshold']]);
                        }
                    }
                    $lat = $visit->gps_debut_lat;
                    $lng = $visit->gps_debut_lng;
                    $targetLat = $visit->contact?->gps_lat;
                    $targetLng = $visit->contact?->gps_lng;
                    if ($rules['ÉCART_GPS']['enabled'] && $this->validPoint($lat, $lng) && $this->validPoint($targetLat, $targetLng)) {
                        $h = sin(deg2rad($targetLat - $lat) / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($targetLat)) * sin(deg2rad($targetLng - $lng) / 2) ** 2;
                        $meters = round(6371000 * 2 * atan2(sqrt(max(0, $h)), sqrt(max(0, 1 - $h))));
                        if ($meters > $rules['ÉCART_GPS']['threshold']) {
                            $this->record($visit->user_id, $visit->id, 'ÉCART_GPS', 'MODERE', 'Le pointage est éloigné du praticien référencé.', ['distance_meters' => $meters, 'threshold_meters' => $rules['ÉCART_GPS']['threshold'], 'lat' => $lat, 'lng' => $lng, 'target_lat' => $targetLat, 'target_lng' => $targetLng]);
                        }
                    }
                }
            });
        $balances = SampleMovement::whereIn('user_id', $ids)->get()->groupBy(fn ($m) => implode('|', [$m->user_id, $m->product_id, $m->lot_number]));
        foreach ($balances as $items) {
            $balance = $items->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity') - $items->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');
            if ($balance < 0) {
                $first = $items->first();
                $this->record($first->user_id, null, 'ÉCHANTILLON_ANOMALIE', 'CRITIQUE', 'Le solde du lot est négatif.', ['product_id' => $first->product_id, 'lot' => $first->lot_number, 'balance' => $balance]);
            }
        }
    }

    private function validPoint($lat, $lng): bool
    {
        return $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ! ($lat == 0 && $lng == 0);
    }

    private function record(string $userId, ?string $activityId, string $type, string $severity, string $description, array $evidence): void
    {
        $fingerprint = hash('sha256', json_encode([$userId, $activityId, $type, $evidence]));
        ComplianceFinding::firstOrCreate(['fingerprint' => $fingerprint], ['user_id' => $userId, 'activity_id' => $activityId,
            'type' => $type, 'severity' => $severity, 'description' => $description, 'evidence' => $evidence, 'detected_at' => now()]);
    }
}
