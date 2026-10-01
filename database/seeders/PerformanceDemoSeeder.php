<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Données de démo pour tester la performance d'équipe et les visites DUO.
 * Non appelé par DatabaseSeeder : php artisan db:seed --class=PerformanceDemoSeeder
 *
 * Génère, pour chaque délégué encadré, ~12 visites par jour ouvré des 5 dernières semaines jusqu'à la fin du mois :
 * réalisées (ou manquées) sur les jours passés, mix aujourd'hui, planifiées ensuite.
 */
class PerformanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $visitsPerDay = (int) (DB::table('settings')->where('key', 'visits_per_day')->value('value') ?: 12);

        $delegates = User::withoutGlobalScopes()->whereNotNull('manager_id')->get();
        $contacts = Contact::withoutGlobalScopes()->whereNotNull('account_id')->get(['id', 'account_id']);
        if ($delegates->isEmpty() || $contacts->isEmpty()) {
            $this->command?->warn('Aucun délégué encadré ou aucun contact : rien à générer.');
            return;
        }

        // Taux de réalisation différent par délégué pour avoir un vrai classement
        $completionRates = [0.95, 0.85, 0.75, 0.65, 0.55, 0.40];
        $notes = ['Présentation produit', 'Suivi de prescription', 'Remise d\'échantillons', 'Relance après congrès', 'Visite de courtoisie'];

        $today = now()->startOfDay();
        $start = now()->subWeeks(5)->startOfWeek();
        $end = now()->endOfMonth();

        // Repartir de zéro sur la période pour pouvoir relancer le seeder
        DB::table('activities')
            ->whereIn('user_id', $delegates->pluck('id'))
            ->whereBetween('date_activite', [$start->toDateString(), $end->toDateString()])
            ->delete();

        $rows = [];
        foreach ($delegates->values() as $i => $delegate) {
            $rate = $completionRates[$i % count($completionRates)];

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                for ($slot = 0; $slot < $visitsPerDay; $slot++) {
                    $startTime = $day->copy()->setTime(8, 0)->addMinutes($slot * 40);

                    if ($day->lt($today)) {
                        $statut = mt_rand(1, 100) <= $rate * 100 ? 'realisee' : 'manquee';
                    } elseif ($day->eq($today)) {
                        $statut = $slot < $visitsPerDay / 2 ? 'realisee' : 'planifiee';
                    } else {
                        $statut = 'planifiee';
                    }

                    $contact = $contacts->random();
                    $rows[] = [
                        'id'            => (string) Str::orderedUuid(),
                        'user_id'       => $delegate->id,
                        'contact_id'    => $contact->id,
                        'account_id'    => $contact->account_id,
                        'activity_type' => 'visite',
                        'date_activite' => $day->toDateString(),
                        'heure_debut'   => $startTime->format('H:i'),
                        'heure_fin'     => $startTime->copy()->addMinutes(30)->format('H:i'),
                        'duree_minutes' => 30,
                        'statut'        => $statut,
                        'notes'         => $notes[array_rand($notes)],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activities')->insert($chunk);
        }

        $this->command?->info(count($rows) . ' visites de démo générées pour ' . $delegates->count() . ' délégués.');
    }
}
