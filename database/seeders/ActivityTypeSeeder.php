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
            ['id' => Str::uuid(), 'name' => 'Visite Médicale', 'code' => 'VIS_MED', 'description' => 'Visite médicale individuelle auprès d\'un prescripteur'],
            ['id' => Str::uuid(), 'name' => 'Table Ronde', 'code' => 'TABLE_RONDE', 'description' => 'Symposium ou table ronde avec plusieurs prescripteurs'],
            ['id' => Str::uuid(), 'name' => 'Relation Publique', 'code' => 'REL_PUB', 'description' => 'Événement et relations publiques'],
            ['id' => Str::uuid(), 'name' => 'STAFF Hospitalier', 'code' => 'STAFF_HOSP', 'description' => 'Staff hospitalier réunissant plusieurs professionnels'],
            ['id' => Str::uuid(), 'name' => 'Visite Officine', 'code' => 'VIS_OFF', 'description' => 'Visite en pharmacie d\'officine'],
            ['id' => Str::uuid(), 'name' => 'Coaching', 'code' => 'COACHING', 'description' => 'Accompagnement sur le terrain pour améliorer les compétences'],
            ['id' => Str::uuid(), 'name' => 'Formation', 'code' => 'FORMATION', 'description' => 'Session de formation pratique sur le terrain'],
            ['id' => Str::uuid(), 'name' => 'Commerciale', 'code' => 'COM', 'description' => 'Activité commerciale ou négociation'],
        ];

        foreach ($items as $item) {
            ActivityType::updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
