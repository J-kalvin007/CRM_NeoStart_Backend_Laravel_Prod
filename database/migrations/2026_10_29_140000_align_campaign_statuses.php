<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL's original ENUM rejects the statuses used by the API. Widen before converting.
        Schema::table('campaigns', fn (Blueprint $table) => $table->string('statut', 30)->default('brouillon')->change());
        foreach (['creation' => 'brouillon', 'deploiement' => 'active', 'execution' => 'active',
            'suivi' => 'active', 'analyse' => 'active', 'cloture' => 'terminee'] as $old => $new) {
            DB::table('campaigns')->where('statut', $old)->update(['statut' => $new]);
        }
    }

    public function down(): void
    {
        // Keep the widened column: restoring the restrictive ENUM would destroy valid statuses.
    }
};
