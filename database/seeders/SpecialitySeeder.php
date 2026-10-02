<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Speciality;
use Illuminate\Support\Str;

class SpecialitySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Cardiologie', 'code' => 'CARD'],
            ['id' => Str::uuid(), 'name' => 'Pédiatrie', 'code' => 'PED'],
            ['id' => Str::uuid(), 'name' => 'Généraliste', 'code' => 'GEN']
        ];

        foreach ($items as $item) {
            Speciality::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
