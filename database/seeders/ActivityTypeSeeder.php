<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityType;
use Illuminate\Support\Str;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'One to One',
                'code' => 'one_to_one',
                'is_multi' => false,
                'description' => 'Visite médicale individuel'
            ],
            [
                'name' => 'STAFF',
                'code' => 'staff',
                'is_multi' => true,
                'description' => 'Staff hospitalier réunissant plusieurs professionnels'
            ],
            [
                'name' => 'Négociation de commande',
                'code' => 'negociation',
                'is_multi' => false,
                'description' => 'Négociation commerciale et commande'
            ],
            [
                'name' => 'Table Ronde',
                'code' => 'TABLE_RONDE',
                'is_multi' => true,
                'description' => 'Symposium ou table ronde avec plusieurs prescripteurs'
            ],
            [
                'name' => 'Relation Publique',
                'code' => 'rp',
                'is_multi' => false,
                'description' => 'Événement et relations publiques'
            ],
        ];

        foreach ($items as $item) {
            ActivityType::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'is_multi' => $item['is_multi'],
                    'description' => $item['description']
                ]
            );
        }
    }
}
