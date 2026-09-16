<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Potential;
use Illuminate\Support\Str;

class PotentialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Fort', 'code' => 'HIGH'],
            ['id' => Str::uuid(), 'name' => 'Moyen', 'code' => 'MED'],
            ['id' => Str::uuid(), 'name' => 'Faible', 'code' => 'LOW']
        ];

        foreach ($items as $item) {
            Potential::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
