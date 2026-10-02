<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Contact, TourPlan, Activity};
use Carbon\Carbon;
use Illuminate\Support\Str;

class AgendaTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (!$admin) {
            $this->command->error("Aucun utilisateur trouvé.");
            return;
        }

        $contacts = Contact::limit(5)->get();
        if ($contacts->isEmpty()) {
            $this->command->error("Aucun contact trouvé. Veuillez exécuter FullTestDataSeeder d'abord.");
            return;
        }

        $tourPlan = TourPlan::firstOrCreate(['nom' => 'Tournée de test Agenda'], [
            'user_id' => $admin->id,
            'date_debut' => Carbon::now()->startOfWeek(),
            'date_fin' => Carbon::now()->endOfWeek(),
            'statut' => 'en_cours'
        ]);

        $startOfWeek = Carbon::now()->startOfWeek();

        // Nettoyer les anciennes activités de la semaine pour ne pas fausser les stats
        Activity::whereBetween('date_activite', [
            $startOfWeek->copy()->format('Y-m-d'), 
            $startOfWeek->copy()->endOfWeek()->format('Y-m-d')
        ])->delete();

        $activitiesData = [];

        // 1. 14 visites confirmées (12 visites régulières, 2 EPU/Réunions)
        for ($i = 0; $i < 12; $i++) {
            $contact = $contacts->random();
            $activitiesData[] = [
                'id' => Str::uuid(),
                'tour_plan_id' => $tourPlan->id,
                'contact_id' => $contact->id,
                'user_id' => $admin->id,
                'activity_type' => 'visite',
                'date_activite' => $startOfWeek->copy()->addDays(rand(0, 4))->format('Y-m-d'),
                'heure_debut' => sprintf('%02d:00', rand(8, 16)),
                'heure_fin' => sprintf('%02d:30', rand(8, 16)), // Simplifié, pas forcément très exact
                'statut' => rand(0, 1) ? 'planifiee' : 'realisee',
                'notes' => 'Visite de routine',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // 2 EPU / Réunions
        for ($i = 0; $i < 2; $i++) {
            $contact = $contacts->random();
            $activitiesData[] = [
                'id' => Str::uuid(),
                'tour_plan_id' => $tourPlan->id,
                'contact_id' => $contact->id,
                'user_id' => $admin->id,
                'activity_type' => 'epu',
                'date_activite' => $startOfWeek->copy()->addDays(rand(0, 4))->format('Y-m-d'),
                'heure_debut' => sprintf('%02d:00', rand(17, 19)),
                'heure_fin' => sprintf('%02d:00', rand(18, 20)),
                'statut' => 'planifiee',
                'notes' => 'Staff / EPU',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // 2 autres (Total = 16. Attends, il en faut 18 en tout, dont 14 confirmées/planifiées. Si on compte EPU comme visite confirmée, ça fait 12+2=14. On rajoute 4 annulées/brouillon pour arriver à 18)
        for ($i = 0; $i < 4; $i++) {
            $contact = $contacts->random();
            $activitiesData[] = [
                'id' => Str::uuid(),
                'tour_plan_id' => $tourPlan->id,
                'contact_id' => $contact->id,
                'user_id' => $admin->id,
                'activity_type' => 'visite',
                'date_activite' => $startOfWeek->copy()->addDays(rand(0, 4))->format('Y-m-d'),
                'heure_debut' => sprintf('%02d:00', rand(9, 15)),
                'heure_fin' => sprintf('%02d:00', rand(10, 16)),
                'statut' => 'annulee',
                'notes' => 'Médecin absent',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        Activity::insert($activitiesData);

        $this->command->info("AgendaTestDataSeeder : 18 activités générées pour la semaine courante.");
    }
}
