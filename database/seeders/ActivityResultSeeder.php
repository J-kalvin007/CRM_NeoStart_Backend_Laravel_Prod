<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityResult;
use Illuminate\Support\Str;

class ActivityResultSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Positif', 'code' => 'POS'],
            ['id' => Str::uuid(), 'name' => 'Neutre', 'code' => 'NEU'],
            ['id' => Str::uuid(), 'name' => 'Objection', 'code' => 'OBJ']
        ];

        foreach ($items as $item) {
            ActivityResult::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
