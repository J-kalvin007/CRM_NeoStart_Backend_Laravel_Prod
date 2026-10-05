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
            // Objectif terrain : 12 visites/jour × 5 jours = 60/semaine, 240/mois
            ['key' => 'visits_per_day', 'value' => '12'],
            ['key' => 'working_days_per_week', 'value' => '5'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
