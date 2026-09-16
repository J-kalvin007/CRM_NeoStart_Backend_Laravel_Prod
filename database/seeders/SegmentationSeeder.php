<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Segmentation;
use Illuminate\Support\Str;

class SegmentationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['id' => Str::uuid(), 'name' => 'Segment A', 'code' => 'A'],
            ['id' => Str::uuid(), 'name' => 'Segment B', 'code' => 'B'],
            ['id' => Str::uuid(), 'name' => 'Segment C', 'code' => 'C']
        ];

        foreach ($items as $item) {
            Segmentation::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
