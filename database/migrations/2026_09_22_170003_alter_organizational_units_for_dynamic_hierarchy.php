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
        Schema::table('organizational_units', function (Blueprint $table) {
            // Nouvelles colonnes pour la hiérarchie dynamique
            $table->uuid('organization_config_id')->nullable()->after('id');
            $table->uuid('level_definition_id')->nullable()->after('organization_config_id');
            $table->integer('level_order')->nullable()->after('level_definition_id');
            $table->string('level_code')->nullable()->after('level_order');
            $table->boolean('is_active')->default(true)->after('metadata');
            $table->integer('display_order')->default(0)->after('is_active');
            
            // Index pour les performances
            $table->index(['organization_config_id', 'level_order'], 'idx_org_level');
            $table->index(['parent_id', 'is_active'], 'idx_parent_active');
            $table->index(['level_code', 'is_active'], 'idx_level_active');
            
            // Clés étrangères
            $table->foreign('organization_config_id')
                  ->references('id')
                  ->on('organization_configs')
                  ->onDelete('cascade');
                  
            $table->foreign('level_definition_id')
                  ->references('id')
                  ->on('hierarchy_level_definitions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            // Supprimer les clés étrangères
            $table->dropForeign(['organization_config_id']);
            $table->dropForeign(['level_definition_id']);
            
            // Supprimer les index
            $table->dropIndex('idx_org_level');
            $table->dropIndex('idx_parent_active');
            $table->dropIndex('idx_level_active');
            
            // Supprimer les colonnes
            $table->dropColumn([
                'organization_config_id',
                'level_definition_id', 
                'level_order',
                'level_code',
                'is_active',
                'display_order'
            ]);
        });
    }
};