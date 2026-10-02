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
        Schema::create('hierarchy_level_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_config_id');
            $table->integer('level_order'); // 1, 2, 3, 4, 5...
            $table->string('level_name'); // "Région", "Préfecture", "Commune"
            $table->string('level_code'); // "region", "prefecture", "commune"
            $table->string('level_icon')->nullable(); // "globe", "map-pin", "building"
            $table->string('level_color')->default('#0066FF'); // Couleur pour l'affichage
            $table->boolean('can_have_users')->default(true);
            $table->boolean('can_have_managers')->default(true);
            $table->boolean('is_required')->default(true);
            $table->json('display_settings')->nullable(); // Paramètres d'affichage
            $table->json('validation_rules')->nullable(); // Règles de validation
            $table->timestamps();

            $table->foreign('organization_config_id')
                  ->references('id')
                  ->on('organization_configs')
                  ->onDelete('cascade');

            $table->unique(['organization_config_id', 'level_order'], 'org_level_order_unique');
            $table->unique(['organization_config_id', 'level_code'], 'org_level_code_unique');
            $table->index(['organization_config_id', 'level_order'], 'idx_hierarchy_level_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hierarchy_level_definitions');
    }
};