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
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Permet d'avoir des paramètres spécifiques par filiale (tenant)
            // Si subsidiary_id est null, c'est un paramètre global.
            $table->foreignUuid('subsidiary_id')
                  ->nullable()
                  ->constrained('subsidiaries')
                  ->cascadeOnDelete();
                  
            $table->string('key');
            $table->text('value')->nullable();
            
            $table->timestamps();
            
            // Une clé doit être unique au sein d'une même filiale (ou globale si null)
            $table->unique(['subsidiary_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
