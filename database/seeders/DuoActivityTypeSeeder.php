<?php

namespace Database\Seeders;

use App\Models\DuoActivityType;
use Illuminate\Database\Seeder;

class DuoActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Coaching',
                'code' => 'COACHING',
                'description' => 'Visite de coaching du délégué par le superviseur',
                'is_active' => true,
            ],
            [
                'name' => 'Formation',
                'code' => 'FORMATION',
                'description' => 'Visite de formation du délégué par le superviseur',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            DuoActivityType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
