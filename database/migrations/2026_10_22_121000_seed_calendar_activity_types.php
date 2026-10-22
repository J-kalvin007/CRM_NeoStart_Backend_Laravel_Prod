<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        foreach (['rcp' => 'RCP', 'reunion' => 'Réunion', 'conge' => 'Congé', 'permission' => 'Permission'] as $code => $name) {
            if (!DB::table('activity_types')->whereRaw('LOWER(code) = ?', [$code])->exists()) {
                DB::table('activity_types')->insert([
                    'id' => (string) Str::uuid(), 'code' => $code, 'name' => $name,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep catalogue rows that may now be referenced by planned activities.
    }
};
