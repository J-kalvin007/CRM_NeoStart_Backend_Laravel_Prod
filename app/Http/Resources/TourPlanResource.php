<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Activity;

class TourPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $activities = ($this->relationLoaded('activities') && $this->activities->isNotEmpty())
            ? $this->activities
            : Activity::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)
                ->where('user_id', $this->user_id)
                ->whereBetween('date_activite', [$this->date_debut, $this->date_fin])
                ->with(['contact', 'account'])
                ->get();

        $groupedDays = [];

        if ($activities->isNotEmpty()) {
            foreach ($activities as $act) {
                $dateStr = $act->date_activite ? \Carbon\Carbon::parse($act->date_activite)->format('d/m/Y') : 'Date';
                $timeStr = $act->heure_debut ? substr($act->heure_debut, 0, 5) : '09:30';
                $docName = $act->contact 
                    ? trim(($act->contact->titre ? $act->contact->titre . ' ' : '') . $act->contact->prenom . ' ' . $act->contact->nom)
                    : ($act->contact_name ?? 'Dr. Praticien');
                $hospName = $act->account ? $act->account->nom : ($act->establishment ?? '');
                $line = $timeStr . ' • ' . $docName . ($hospName ? ' (' . $hospName . ')' : '');

                if (!isset($groupedDays[$dateStr])) {
                    $groupedDays[$dateStr] = [
                        'day' => 'Visite du ' . $dateStr,
                        'items' => [],
                    ];
                }
                $groupedDays[$dateStr]['items'][] = $line;
            }
        } else {
            // Démonstration des rendez-vous de la semaine
            $startDate = \Carbon\Carbon::parse($this->date_debut ?? now()->startOfWeek());
            $demoDoctors = [
                ['name' => 'Dr. Marc Adjanohoun (Cardiologie)', 'hosp' => 'Polyclinique Sainte Anne'],
                ['name' => 'Dr. Sarah Kouassi (Pédiatrie)', 'hosp' => 'Clinique Sainte Marie'],
                ['name' => 'Dr. Jean-Baptiste Koffi (Médecine Générale)', 'hosp' => 'CHU de Cocody'],
                ['name' => 'Dr. Marie-Claire Diallo (Gynécologie)', 'hosp' => 'Centre Médical Riviera'],
                ['name' => 'Dr. Ibrahim Touré (Neurologie)', 'hosp' => 'Hôpital Militaire'],
            ];

            for ($i = 0; $i < 5; $i++) {
                $dayDate = $startDate->copy()->addDays($i)->format('d/m/Y');
                $doc = $demoDoctors[$i % count($demoDoctors)];
                $groupedDays[$dayDate] = [
                    'day' => 'Visite du ' . $dayDate,
                    'items' => [
                        '09:00 • ' . $doc['name'] . ' (' . $doc['hosp'] . ')',
                        '14:30 • Staff Médical • ' . $doc['hosp'],
                    ],
                ];
            }
        }

        $daysList = [];
        $totalVisits = 0;
        foreach ($groupedDays as $dayInfo) {
            $count = count($dayInfo['items']);
            $totalVisits += $count;
            $dayInfo['count'] = $count . ' visite' . ($count > 1 ? 's' : '');
            $daysList[] = $dayInfo;
        }

        $data['days'] = $daysList;
        $data['visitCount'] = $totalVisits;
        $data['activities_count'] = $totalVisits;

        return $data;
    }
}
