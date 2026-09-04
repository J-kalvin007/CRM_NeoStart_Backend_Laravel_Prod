<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->foreignUuid('country_id')->constrained('countries')->restrictOnDelete();
            
            $table->string('nom');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('cible')->nullable();
            $table->text('message')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->json('objectifs')->nullable();
            $table->json('meta')->nullable();

            $table->enum('statut', [
                'creation', 'validation', 'deploiement', 'execution', 'suivi', 'analyse', 'cloture',
            ])->default('creation');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
