<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PharmacovigilanceSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id') ?? DB::table('users')->first()->id ?? null;
        $contactAka = DB::table('contacts')->where('nom', 'LIKE', '%Aka%')->value('id') ?? DB::table('contacts')->first()->id ?? null;
        $prodCardio = DB::table('products')->where('nom', 'LIKE', '%CardioPulse%')->value('id') ?? DB::table('products')->first()->id ?? null;

        if ($delegueId && $contactAka && $prodCardio) {
            DB::table('pharmacovigilance_reports')->insert([
                [
                    'id' => Str::uuid(),
                    'user_id' => $delegueId,
                    'contact_id' => $contactAka,
                    'product_id' => $prodCardio,
                    'patient_initials' => 'J.D.',
                    'patient_age' => 58,
                    'patient_gender' => 'M',
                    'adverse_event_description' => 'Céphalées modérées signalées 2 heures après la première prise de CardioPulse 50mg.',
                    'severity' => 'moderee',
                    'report_date' => now()->subDays(1)->format('Y-m-d'),
                    'status' => 'soumis',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }
    }
}
