<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $team = \App\Models\Team::first();

        // 1. Super Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@crm.local'
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'force_password_change' => false,
            'status' => true,
        ]);
        if (\Spatie\Permission\Models\Role::where('name', 'super-admin')->exists()) {
            $admin->assignRole('super-admin');
        }

        // 2. Manager Principal (COZI Adom Moucharif)
        $managerMoucharif = User::firstOrCreate([
            'email' => 'cmoucharif@gmail.com'
        ], [
            'name' => 'COZI Adom Moucharif (Manager)',
            'password' => Hash::make('password'),
            'phone' => '+225 07 01 02 03 04',
            'force_password_change' => false,
            'status' => true,
            'team_id' => $team->id ?? null,
        ]);
        if (\Spatie\Permission\Models\Role::where('name', 'Manager')->exists()) {
            $managerMoucharif->assignRole('Manager');
        }
        if (\Spatie\Permission\Models\Role::where('name', 'manager')->exists()) {
            $managerMoucharif->assignRole('manager');
        }

        // 3. Second Manager
        $manager = User::firstOrCreate([
            'email' => 'manager@crm.local'
        ], [
            'name' => 'John Manager',
            'password' => Hash::make('password'),
            'phone' => '+225 07 00 00 00 01',
            'force_password_change' => false,
            'status' => true,
            'team_id' => $team->id ?? null,
        ]);
        if (\Spatie\Permission\Models\Role::where('name', 'Manager')->exists()) {
            $manager->assignRole('Manager');
        }

        if ($team) {
            $team->update(['manager_id' => $managerMoucharif->id]);
        }

        // 4. Délégués Rattachés au Manager Moucharif & Équipe Abidjan
        $delegatesData = [
            [
                'email' => 'adommoucha@gmail.com',
                'name' => 'COZI Adom Moucharif',
                'phone' => '+225 07 08 12 34 56',
            ],
            [
                'email' => 'koffi.mensah@skynity.local',
                'name' => 'Koffi Mensah',
                'phone' => '+225 07 09 88 11 22',
            ],
            [
                'email' => 'fatou.ndiaye@skynity.local',
                'name' => 'Fatou Ndiaye',
                'phone' => '+225 05 44 22 11 00',
            ],
            [
                'email' => 'jean.aka@skynity.local',
                'name' => 'Jean-Marc Aka',
                'phone' => '+225 01 23 45 67 89',
            ],
            [
                'email' => 'sophie.amadou@skynity.local',
                'name' => 'Sophie Amadou',
                'phone' => '+225 07 99 88 77 66',
            ],
            [
                'email' => 'delegue@crm.local',
                'name' => 'Alice Déléguée',
                'phone' => '+225 07 11 22 33 44',
            ],
        ];

        foreach ($delegatesData as $data) {
            $del = User::firstOrCreate([
                'email' => $data['email']
            ], [
                'name' => $data['name'],
                'password' => Hash::make('password'),
                'phone' => $data['phone'],
                'manager_id' => $managerMoucharif->id,
                'team_id' => $team->id ?? null,
                'force_password_change' => false,
                'status' => true,
            ]);
            $del->update([
                'manager_id' => $managerMoucharif->id,
                'team_id' => $team->id ?? null,
            ]);

            if (\Spatie\Permission\Models\Role::where('name', 'Délégué Médical')->exists()) {
                $del->assignRole('Délégué Médical');
            }
            if (\Spatie\Permission\Models\Role::where('name', 'delegue')->exists()) {
                $del->assignRole('delegue');
            }
        }
    }
}
