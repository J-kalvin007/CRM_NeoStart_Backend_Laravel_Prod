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
        Schema::table('user_scopes', function (Blueprint $table) {
            // Ajouter des colonnes pour plus de flexibilité
            $table->uuid('organization_config_id')->nullable()->after('organizational_unit_id');
            $table->boolean('is_primary')->default(true)->after('organization_config_id');
            $table->json('permissions')->nullable()->after('is_primary'); // Permissions spécifiques à ce scope
            $table->timestamp('expires_at')->nullable()->after('permissions'); // Scope temporaire
            $table->boolean('is_active')->default(true)->after('expires_at');
            
            // Index pour les performances
            $table->index(['user_id', 'is_active'], 'idx_user_active');
            $table->index(['organizational_unit_id', 'is_active'], 'idx_unit_active');
            $table->index(['organization_config_id', 'is_active'], 'idx_config_active');
            
            // Clé étrangère pour la configuration
            $table->foreign('organization_config_id')
                  ->references('id')
                  ->on('organization_configs')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_scopes', function (Blueprint $table) {
            // Supprimer la clé étrangère
            $table->dropForeign(['organization_config_id']);
            
            // Supprimer les index
            $table->dropIndex('idx_user_active');
            $table->dropIndex('idx_unit_active');
            $table->dropIndex('idx_config_active');
            
            // Supprimer les colonnes
            $table->dropColumn([
                'organization_config_id',
                'is_primary',
                'permissions',
                'expires_at',
                'is_active'
            ]);
        });
    }
};