<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventFormat;
use Illuminate\Support\Str;

class EventFormatSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Congrès', 'code' => 'CONG'],
            ['id' => Str::uuid(), 'name' => 'Conférence', 'code' => 'CONF'],
            ['id' => Str::uuid(), 'name' => 'Webinaire', 'code' => 'WEB']
        ];

        foreach ($items as $item) {
            EventFormat::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
