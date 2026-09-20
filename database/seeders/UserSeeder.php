<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Country;
use App\Models\Subsidiary;
use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::first();
        $subsidiary = Subsidiary::first();
        $team = Team::first();

        $admin = User::firstOrCreate([
            'email' => 'admin@crm.local'
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'country_id' => $country?->id,
            'subsidiary_id' => $subsidiary?->id,
            'team_id' => $team?->id,
            'force_password_change' => false,
            'status' => true,
        ]);
        if (!$admin->hasRole('superadmin')) {
            $admin->assignRole('superadmin');
        }

        $manager = User::firstOrCreate([
            'email' => 'cmoucharif@gmail.com'
        ], [
            'name' => 'John Manager',
            'password' => Hash::make('password'),
            'country_id' => $country?->id,
            'subsidiary_id' => $subsidiary?->id,
            'team_id' => $team?->id,
            'force_password_change' => false,
            'status' => true,
        ]);
        if (!$manager->hasRole('superviseur')) {
            $manager->assignRole('superviseur');
        }

        $delegatesData = [
            [
                'email' => 'adommoucha@gmail.com',
                'name' => 'Alice Delegue',
                'role' => 'delegue_medical',
            ],
            [
                'email' => 'afi.mensah@crm.local',
                'name' => 'Afi Mensah',
                'role' => 'delegue_medical',
            ],
            [
                'email' => 'koffi.agbeko@crm.local',
                'name' => 'Koffi Agbeko',
                'role' => 'delegue_medical',
            ],
            [
                'email' => 'sarah.lawson@crm.local',
                'name' => 'Sarah Lawson',
                'role' => 'delegue_medical',
            ],
            [
                'email' => 'mamadou.kane@crm.local',
                'name' => 'Mamadou Kane',
                'role' => 'delegue_medical',
            ],
            [
                'email' => 'jean.dupont@crm.local',
                'name' => 'Jean Dupont',
                'role' => 'delegue_medical',
            ],
        ];

        foreach ($delegatesData as $dData) {
            $user = User::firstOrCreate([
                'email' => $dData['email']
            ], [
                'name' => $dData['name'],
                'password' => Hash::make('password'),
                'country_id' => $country?->id,
                'subsidiary_id' => $subsidiary?->id,
                'team_id' => $team?->id,
                'manager_id' => $manager?->id,
                'force_password_change' => false,
                'status' => true,
            ]);
            if ($user->manager_id !== $manager?->id) {
                $user->update(['manager_id' => $manager?->id, 'team_id' => $team?->id]);
            }
            if (!$user->hasRole($dData['role'])) {
                $user->assignRole($dData['role']);
            }
        }
    }
}
