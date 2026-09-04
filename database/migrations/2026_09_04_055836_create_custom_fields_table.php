<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre (Le champ peut être global ou spécifique à une filiale)
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            
            // Cible du champ (ex: App\Models\Account, App\Models\Contact, etc.)
            $table->string('model_type', 100);
            
            // Propriétés du champ
            $table->string('name', 50); // Nom technique (ex: num_lits)
            $table->string('label'); // Nom d'affichage (ex: Nombre de lits)
            $table->enum('type', ['text', 'textarea', 'number', 'date', 'boolean', 'select', 'multiselect'])->default('text');
            $table->json('options')->nullable(); // Pour les select (ex: ["Option A", "Option B"])
            
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0); // Ordre d'affichage
            
            $table->timestamps();
            
            // Un nom de champ doit être unique par modèle et par filiale
            $table->unique(['subsidiary_id', 'model_type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
