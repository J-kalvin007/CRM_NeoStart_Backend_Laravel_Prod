<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSettings = [
            ['key' => 'app_name', 'value' => 'Skynity CRM'],
            ['key' => 'primary_color', 'value' => '#2563eb'],
            ['key' => 'logo_path', 'value' => ''],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'subsidiary_id' => null],
                ['value' => $setting['value']]
            );
        }
    }
}
