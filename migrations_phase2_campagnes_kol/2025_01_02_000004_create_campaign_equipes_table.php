<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_equipes', function (Blueprint $table) {
            $table->id();
            // Ressources mobilisées sur la campagne
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_equipes');
    }
};
