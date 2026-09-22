<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Account;
use App\Models\User;
use App\Models\Potential;
use App\Models\Influence;
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::first();
        $user = User::first();
        $medecinType = ContactType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médecin']);
        $medecinType = ContactType::firstOrCreate(['code' => 'MED'], ['libelle' => 'Médecin']);

        $potHigh = Potential::where('code', 'HIGH')->first();
        $potMed = Potential::where('code', 'MED')->first();
        $infHigh = Influence::where('code', 'HIGH')->first();
        $infMed = Influence::where('code', 'MED')->first();
        $infLow = Influence::where('code', 'LOW')->first();

        $contacts = [
            [
                'nom' => 'Koffi',
                'prenom' => 'Jean-Paul',
                'titre' => 'Professeur',
                'telephone' => '+228 90 12 34 56',
                'email' => 'jp.koffi@chu-sylvanus.tg',
                'potential_id' => $potHigh?->id,
                'influence_id' => $infHigh?->id,
                'score' => 85,
                'gps_lat' => 6.1375,
                'gps_lng' => 1.2125,
                'statut' => 'actif',
                'meta' => ['specialite' => 'Cardiologie', 'rpps' => '10002938475']
            ],
            [
                'nom' => 'Lawson',
                'prenom' => 'Ablavi Amina',
                'titre' => 'Docteur',
                'telephone' => '+228 91 88 77 66',
                'email' => 'a.lawson@pediatrie-lome.tg',
                'potential_id' => $potHigh?->id,
                'influence_id' => $infMed?->id,
                'score' => 78,
                'gps_lat' => 6.1320,
                'gps_lng' => 1.2050,
                'statut' => 'actif',
                'meta' => ['specialite' => 'Pédiatrie', 'rpps' => '10004928172']
            ],
            [
                'nom' => 'Adjanohoun',
                'prenom' => 'Marc',
                'titre' => 'Docteur',
                'telephone' => '+228 92 33 44 55',
                'email' => 'm.adjanohoun@gmail.com',
                'potential_id' => $potMed?->id,
                'influence_id' => $infLow?->id,
                'score' => 60,
                'gps_lat' => 6.1280,
                'gps_lng' => 1.2220,
                'statut' => 'actif',
                'meta' => ['specialite' => 'Médecine Générale', 'rpps' => '10005839201']
            ]
        ];

        $users = User::all();
        if ($users->isNotEmpty()) {
            foreach ($users as $u) {
                foreach ($contacts as $c) {
                    Contact::firstOrCreate(
                        ['email' => $u->id . '_' . $c['email']],
                        array_merge($c, [
                            'email' => $c['email'],
                            'account_id' => $account?->id,
                            'user_id' => $u->id,
                            'contact_type_id' => $medecinType?->id
                        ])
                    );
                }
            }
        }
    }
}
