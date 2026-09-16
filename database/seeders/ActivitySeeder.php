<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\Account;
use App\Models\Contact;
use App\Models\User;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $account = Account::first();
        $contact = Contact::first();

        $activities = [
            [
                'activity_type' => 'visite',
                'statut' => 'realisee',
                'date_activite' => now()->subDays(2)->toDateString(),
                'heure_debut' => '09:00:00',
                'heure_fin' => '09:35:00',
                'notes' => 'Présentation du CardioProtect 50mg. Intérêt vif du médecin sur l\'efficacité hypertensive.',
                'gps_debut_lat' => 6.1375,
                'gps_debut_lng' => 1.2125,
                'gps_fin_lat' => 6.1376,
                'gps_fin_lng' => 1.2126,
            ],
            [
                'activity_type' => 'visite',
                'statut' => 'en_cours',
                'date_activite' => now()->toDateString(),
                'heure_debut' => '10:30:00',
                'notes' => 'Visite à la Pharmacie de l\'Indépendance. Contrôle du niveau de stock officinal.',
                'gps_debut_lat' => 6.1280,
                'gps_debut_lng' => 1.2220,
            ],
            [
                'activity_type' => 'visite',
                'statut' => 'planifiee',
                'date_activite' => now()->addDays(1)->toDateString(),
                'heure_debut' => '14:00:00',
                'notes' => 'Rendez-vous fixé avec le Dr Lawson pour démonstration du PediaCure Sirop.',
            ]
        ];

        if ($user && $account) {
            foreach ($activities as $act) {
                Activity::create(array_merge($act, [
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'contact_id' => $contact?->id
                ]));
            }
        }
    }
}
