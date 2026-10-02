<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuoActivityTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Coaching',
                'description' => 'Accompagnement sur le terrain pour améliorer les compétences',
                'code' => 'coaching',
            ],
            [
                'name' => 'Formation',
                'description' => 'Session de formation pratique sur le terrain',
                'code' => 'formation',
            ],
        ];

        foreach ($types as $type) {
            DB::table('activity_types')->updateOrInsert(
                ['code' => $type['code']],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Types d\'activité duo créés avec succès.');
    }
}
