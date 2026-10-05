<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add country_id to accounts
        if (!Schema::hasColumn('accounts', 'country_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
            // Migrate from subsidiaries if available
            if (Schema::hasColumn('accounts', 'subsidiary_id') && Schema::hasTable('subsidiaries')) {
                DB::statement("UPDATE accounts a JOIN subsidiaries s ON a.subsidiary_id = s.id SET a.country_id = s.country_id");
            }
        }
        
        // 2. Add country_id to documents
        if (!Schema::hasColumn('documents', 'country_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
        }
        
        // 3. Add country_id to objectifs
        if (!Schema::hasColumn('objectifs', 'country_id')) {
            Schema::table('objectifs', function (Blueprint $table) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
        }
        
        // 4. Add country_id to products
        if (!Schema::hasColumn('products', 'country_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
        }
        
        // Ensure teams was handled if it exists
        if (Schema::hasTable('teams') && !Schema::hasColumn('teams', 'country_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not providing down since we are moving forward
    }
};
