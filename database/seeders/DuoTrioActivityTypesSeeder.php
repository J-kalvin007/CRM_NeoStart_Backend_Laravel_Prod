<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DuoActivityType;
use App\Models\TrioActivityType;

class DuoTrioActivityTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Duo Activity Types
        DuoActivityType::updateOrCreate(
            ['code' => 'formation'],
            [
                'name' => 'Formation',
                'description' => 'Visite de formation en duo avec un superviseur',
                'is_active' => true,
            ]
        );

        DuoActivityType::updateOrCreate(
            ['code' => 'accompagnement'],
            [
                'name' => 'Accompagnement',
                'description' => 'Visite d\'accompagnement avec un superviseur',
                'is_active' => true,
            ]
        );

        DuoActivityType::updateOrCreate(
            ['code' => 'evaluation'],
            [
                'name' => 'Évaluation',
                'description' => 'Visite d\'évaluation par un superviseur',
                'is_active' => true,
            ]
        );

        // Seed Trio Activity Types
        TrioActivityType::updateOrCreate(
            ['code' => 'formation_complete'],
            [
                'name' => 'Formation Complète',
                'description' => 'Visite de formation en trio avec un Sales Manager et un superviseur',
                'is_active' => true,
            ]
        );

        TrioActivityType::updateOrCreate(
            ['code' => 'evaluation_complete'],
            [
                'name' => 'Évaluation Complète',
                'description' => 'Visite d\'évaluation en trio avec un Sales Manager et un superviseur',
                'is_active' => true,
            ]
        );
    }
}
