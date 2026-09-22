<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => 'admin@crm.local'
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'force_password_change' => false,
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');

        $manager = User::firstOrCreate([
            'email' => 'manager@crm.local'
        ], [
            'name' => 'John Manager',
            'password' => Hash::make('password'),
            'force_password_change' => false,
            'is_active' => true,
        ]);
        $manager->assignRole('Superviseur');

        $delegue = User::firstOrCreate([
            'email' => 'delegue@crm.local'
        ], [
            'name' => 'Alice Delegue',
            'password' => Hash::make('password'),
            'force_password_change' => false,
            'is_active' => true,
        ]);
        $delegue->assignRole('delegue');
    }
}
