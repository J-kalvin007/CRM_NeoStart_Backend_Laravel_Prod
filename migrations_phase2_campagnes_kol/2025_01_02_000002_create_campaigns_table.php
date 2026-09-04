<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pays_id')->constrained('pays')->restrictOnDelete();
            $table->string('nom');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('cible')->nullable();
            $table->text('message')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->json('objectifs')->nullable();

            $table->enum('statut', [
                'creation', 'validation', 'deploiement', 'execution', 'suivi', 'analyse', 'cloture',
            ])->default('creation');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
