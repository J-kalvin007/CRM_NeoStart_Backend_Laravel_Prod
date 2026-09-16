<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Subsidiary;
use App\Models\User;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $subsidiary = Subsidiary::first();
        $user = User::first();

        $hopitalType = AccountType::firstOrCreate(['code' => 'HOP'], ['libelle' => 'Hôpital']);
        $pharmaType = AccountType::firstOrCreate(['code' => 'PHA'], ['libelle' => 'Pharmacie']);
        $cliniqueType = AccountType::firstOrCreate(['code' => 'CLI'], ['libelle' => 'Clinique']);

        $accounts = [
            [
                'nom' => 'CHU Sylvanus Olympio',
                'account_type_id' => $hopitalType->id,
                'adresse' => 'Boulevard 13 Janvier',
                'ville' => 'Lomé',
                'telephone' => '+228 22 21 25 01',
                'email' => 'contact@chu-sylvanus.tg',
                'gps_lat' => 6.1375,
                'gps_lng' => 1.2125,
                'statut' => 'actif'
            ],
            [
                'nom' => 'Centre Hospitalier Régional (CHR) Maritime',
                'account_type_id' => $hopitalType->id,
                'adresse' => 'Route de Tsévié',
                'ville' => 'Tsévié',
                'telephone' => '+228 22 30 11 22',
                'email' => 'chr.maritime@sante.gouv.tg',
                'gps_lat' => 6.4250,
                'gps_lng' => 1.2167,
                'statut' => 'actif'
            ],
            [
                'nom' => 'Pharmacie de l\'Indépendance',
                'account_type_id' => $pharmaType->id,
                'adresse' => 'Avenue de la Libération',
                'ville' => 'Lomé',
                'telephone' => '+228 22 21 00 99',
                'email' => 'contact@pharmacie-independance.tg',
                'gps_lat' => 6.1280,
                'gps_lng' => 1.2220,
                'statut' => 'actif'
            ],
            [
                'nom' => 'Pharmacie du Grand Marché',
                'account_type_id' => $pharmaType->id,
                'adresse' => 'Rue du Commerce',
                'ville' => 'Lomé',
                'telephone' => '+228 22 22 45 67',
                'email' => 'grandmarche.pharma@gmail.com',
                'gps_lat' => 6.1260,
                'gps_lng' => 1.2150,
                'statut' => 'actif'
            ],
            [
                'nom' => 'Clinique Bio-Santé International',
                'account_type_id' => $cliniqueType->id,
                'adresse' => 'Quartier Nyékonakpoè',
                'ville' => 'Lomé',
                'telephone' => '+228 22 26 88 99',
                'email' => 'info@biosante-clinique.tg',
                'gps_lat' => 6.1320,
                'gps_lng' => 1.2050,
                'statut' => 'actif'
            ],
        ];

        if ($subsidiary && $user) {
            foreach ($accounts as $acc) {
                Account::firstOrCreate(
                    ['nom' => $acc['nom']],
                    array_merge($acc, [
                        'subsidiary_id' => $subsidiary->id,
                        'user_id' => $user->id
                    ])
                );
            }
        }
    }
}
