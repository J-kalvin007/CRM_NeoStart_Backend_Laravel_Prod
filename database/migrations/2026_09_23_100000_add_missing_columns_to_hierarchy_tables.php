<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes manquantes à hierarchy_level_definitions
        Schema::table('hierarchy_level_definitions', function (Blueprint $table) {
            if (!Schema::hasColumn('hierarchy_level_definitions', 'level_description')) {
                $table->text('level_description')->nullable()->after('level_code');
            }
            if (!Schema::hasColumn('hierarchy_level_definitions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_required');
            }
            if (!Schema::hasColumn('hierarchy_level_definitions', 'metadata')) {
                $table->json('metadata')->nullable()->after('validation_rules');
            }
        });

        // Ajouter les colonnes manquantes à organization_configs (si elles n'existent pas)
        Schema::table('organization_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_configs', 'metadata')) {
                $table->json('metadata')->nullable()->after('settings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_level_definitions', function (Blueprint $table) {
            $table->dropColumn(['level_description', 'is_active', 'metadata']);
        });

        Schema::table('organization_configs', function (Blueprint $table) {
            $table->dropColumn(['metadata']);
        });
    }
};
