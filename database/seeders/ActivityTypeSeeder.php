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
            ['id' => Str::uuid(), 'name' => 'Visite Médicale', 'code' => 'VIS_MED'],
            ['id' => Str::uuid(), 'name' => 'Commerciale', 'code' => 'COM'],
            ['id' => Str::uuid(), 'name' => 'Pharmacie', 'code' => 'PHARMA']
        ];

        foreach ($items as $item) {
            ActivityType::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
