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

        $delegue = User::firstOrCreate([
            'email' => 'adommoucha@gmail.com'
        ], [
            'name' => 'Alice Delegue',
            'password' => Hash::make('password'),
            'country_id' => $country?->id,
            'subsidiary_id' => $subsidiary?->id,
            'team_id' => $team?->id,
            'force_password_change' => false,
            'status' => true,
        ]);
        if (!$delegue->hasRole('delegue_medical')) {
            $delegue->assignRole('delegue_medical');
        }
    }
}
