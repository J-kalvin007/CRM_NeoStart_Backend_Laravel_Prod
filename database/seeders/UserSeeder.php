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

        // 1. Super Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@crm.local'
        ], [
            'name' => 'Super Administrateur',
            'password' => Hash::make('password'),
            'phone' => '+225 07 00 00 00 00',
            'force_password_change' => false,
            'status' => true,
        ]);
        if (!$admin->hasRole('superadmin')) {
            $admin->assignRole('superadmin');
        }

        // 2. Manager Principal (COZI Adom Moucharif)
        $manager = User::firstOrCreate([
            'email' => 'cmoucharif@gmail.com'
        ], [
            'name' => 'COZI Adom Moucharif (Manager)',
            'password' => Hash::make('password'),
            'phone' => '+225 07 01 02 03 04',
            'force_password_change' => false,
            'status' => true,
        ]);
        foreach (['superviseur', 'manager', 'Manager'] as $r) {
            if (\Spatie\Permission\Models\Role::where('name', $r)->exists() && !$manager->hasRole($r)) {
                $manager->assignRole($r);
            }
        }
        if ($team) {
            $team->update(['manager_id' => $manager->id]);
        }

        // 3. Délégués
        $delegatesData = [
            [
                'email' => 'adommoucha@gmail.com',
                'name' => 'COZI Adom Moucharif (Délégué)',
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
                'manager_id' => $manager?->id,
                'force_password_change' => false,
                'status' => true,
            ]);
            $user->update([
                'manager_id' => $manager?->id,
            ]);
            foreach ([$dData['role'], 'delegue', 'Délégué Médical'] as $r) {
                if (\Spatie\Permission\Models\Role::where('name', $r)->exists() && !$user->hasRole($r)) {
                    $user->assignRole($r);
                }
            }
        }
    }
}
