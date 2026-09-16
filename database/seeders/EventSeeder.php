<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\User;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        $events = [
            [
                'title' => 'Congrès National de Cardiologie 2026',
                'description' => 'Symposium de présentation des résultats d\'études cliniques sur CardioProtect.',
                'type' => 'congress',
                'start_time' => now()->addDays(15)->toDateTimeString(),
                'end_time' => now()->addDays(17)->toDateTimeString(),
                'location' => 'Palais des Congrès de Lomé',
                'budget' => 2500000.00,
                'status' => 'planned'
            ],
            [
                'title' => 'Staff Médical Pédiatrie - CHU Sylvanus',
                'description' => 'Réunion de présentation produit auprès de l\'équipe médicale du service de pédiatrie.',
                'type' => 'symposium',
                'start_time' => now()->addDays(5)->toDateTimeString(),
                'end_time' => now()->addDays(5)->addHours(2)->toDateTimeString(),
                'location' => 'Salle de Conférence Pédiatrie, CHU Sylvanus',
                'budget' => 150000.00,
                'status' => 'planned'
            ]
        ];

        if ($user) {
            foreach ($events as $ev) {
                Event::create(array_merge($ev, [
                    'user_id' => $user->id,
                    'subsidiary_id' => $user->subsidiary_id
                ]));
            }
        }
    }
}
