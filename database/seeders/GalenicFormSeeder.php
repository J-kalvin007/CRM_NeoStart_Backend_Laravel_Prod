<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GalenicForm;
use Illuminate\Support\Str;

class GalenicFormSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Comprimé', 'code' => 'COMP'],
            ['id' => Str::uuid(), 'name' => 'Sirop', 'code' => 'SIR'],
            ['id' => Str::uuid(), 'name' => 'Injectable', 'code' => 'INJ']
        ];

        foreach ($items as $item) {
            GalenicForm::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
