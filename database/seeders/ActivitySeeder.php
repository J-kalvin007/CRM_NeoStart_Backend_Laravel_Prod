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
        $users = User::all();
        $account = Account::first();
        $contacts = Contact::all();

        $today = now()->toDateString();
        $yesterday = now()->subDays(1)->toDateString();
        $threeDaysAgo = now()->subDays(3)->toDateString();

        if ($users->isNotEmpty() && $account) {
            foreach ($users as $u) {
                $userContacts = $contacts->where('user_id', $u->id)->values();
                $c1 = $userContacts->get(0) ?? $contacts->first();
                $c2 = $userContacts->get(1) ?? $contacts->skip(1)->first() ?? $c1;
                $c3 = $userContacts->get(2) ?? $contacts->last() ?? $c1;

                $activities = [
                    [
                        'activity_type' => 'visite',
                        'statut' => 'realisee',
                        'report_status' => 'submitted',
                        'report_content' => 'Présentation complète de la gamme Cardio. Le praticien s\'est montré très réceptif aux nouvelles données d\'efficacité.',
                        'date_activite' => $today,
                        'heure_debut' => '09:00:00',
                        'heure_fin' => '09:35:00',
                        'notes' => 'DiaBex • Présentation Gamme Cardio & Échantillons',
                        'contact_id' => $c1?->id,
                    ],
                    [
                        'activity_type' => 'visite',
                        'statut' => 'realisee',
                        'report_status' => 'submitted',
                        'report_content' => 'Échanges fructueux sur CardioPulse 50mg. Demande de documentation complémentaire sur les études de tolérance.',
                        'date_activite' => $today,
                        'heure_debut' => '11:15:00',
                        'heure_fin' => '11:50:00',
                        'notes' => 'CardioPulse 50mg • Suivi Posologie Child',
                        'contact_id' => $c2?->id,
                    ],
                    [
                        'activity_type' => 'visite',
                        'statut' => 'en_cours',
                        'report_status' => 'draft',
                        'date_activite' => $today,
                        'heure_debut' => '14:30:00',
                        'heure_fin' => '15:15:00',
                        'notes' => 'CardioPlus • Discussion Étude Clinique',
                        'contact_id' => $c3?->id,
                    ],
                    [
                        'activity_type' => 'visite',
                        'statut' => 'planifiee',
                        'report_status' => 'draft',
                        'date_activite' => $today,
                        'heure_debut' => '16:00:00',
                        'heure_fin' => '16:45:00',
                        'notes' => 'VasoProtect Neo • e-Detailing Brochure',
                        'contact_id' => $c1?->id,
                    ],
                    [
                        'activity_type' => 'visite',
                        'statut' => 'realisee',
                        'report_status' => 'submitted',
                        'report_content' => 'Présentation du nouveau dosage DiaBex. Le médecin a partagé ses retours positifs.',
                        'date_activite' => $yesterday,
                        'heure_debut' => '10:00:00',
                        'heure_fin' => '10:45:00',
                        'notes' => 'DiaBex • Présentation Nouveau Dosage',
                        'contact_id' => $c2?->id,
                    ],
                    [
                        'activity_type' => 'reunion',
                        'statut' => 'realisee',
                        'report_status' => 'validated',
                        'date_activite' => $threeDaysAgo,
                        'heure_debut' => '15:00:00',
                        'heure_fin' => '16:30:00',
                        'notes' => 'Réunion Équipe Médicale & Tournée Régionale',
                        'contact_id' => $c3?->id,
                    ],
                ];

                foreach ($activities as $act) {
                    Activity::create(array_merge($act, [
                        'user_id' => $u->id,
                        'account_id' => $account->id,
                    ]));
                }
            }
        }
    }
}
