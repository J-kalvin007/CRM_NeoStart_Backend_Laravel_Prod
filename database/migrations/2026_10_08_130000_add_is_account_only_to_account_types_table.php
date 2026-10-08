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
        Schema::table('account_types', function (Blueprint $table) {
            if (!Schema::hasColumn('account_types', 'is_account_only')) {
                $table->boolean('is_account_only')->default(false)->after('is_active');
            }
        });

        Schema::table('activity_types', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_types', 'is_account_only')) {
                $table->boolean('is_account_only')->default(false)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            if (Schema::hasColumn('account_types', 'is_account_only')) {
                $table->dropColumn('is_account_only');
            }
        });

        Schema::table('activity_types', function (Blueprint $table) {
            if (Schema::hasColumn('activity_types', 'is_account_only')) {
                $table->dropColumn('is_account_only');
            }
        });
    }
};
