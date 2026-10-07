<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'region_id') && Schema::hasTable('regions')) {
                    $table->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'territory_id') && Schema::hasTable('territories')) {
                    $table->foreignUuid('territory_id')->nullable()->constrained('territories')->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'zone_id') && Schema::hasTable('zones')) {
                    $table->foreignUuid('zone_id')->nullable()->constrained('zones')->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'sector_id') && Schema::hasTable('sectors')) {
                    $table->foreignUuid('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'sector_id')) {
                    try { $table->dropForeign(['sector_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('sector_id');
                }
                if (Schema::hasColumn('users', 'zone_id')) {
                    try { $table->dropForeign(['zone_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('zone_id');
                }
                if (Schema::hasColumn('users', 'territory_id')) {
                    try { $table->dropForeign(['territory_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('territory_id');
                }
                if (Schema::hasColumn('users', 'region_id')) {
                    try { $table->dropForeign(['region_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('region_id');
                }
            });
        }
    }
};
