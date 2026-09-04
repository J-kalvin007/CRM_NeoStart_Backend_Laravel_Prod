<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kol_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->unique()->constrained('contacts')->cascadeOnDelete();
            
            $table->string('expertise')->nullable();
            $table->string('influence_institution')->nullable();
            $table->text('publications')->nullable();
            $table->text('relations_historique')->nullable();
            $table->text('engagement')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kol_profiles');
    }
};
