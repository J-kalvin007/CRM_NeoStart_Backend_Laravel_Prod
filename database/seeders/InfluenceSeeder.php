<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Influence;
use Illuminate\Support\Str;

class InfluenceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'KOL Régional', 'code' => 'KOL_REG'],
            ['id' => Str::uuid(), 'name' => 'Chef de service', 'code' => 'CHEF_SRV'],
            ['id' => Str::uuid(), 'name' => 'Formateur', 'code' => 'FORM']
        ];

        foreach ($items as $item) {
            Influence::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
