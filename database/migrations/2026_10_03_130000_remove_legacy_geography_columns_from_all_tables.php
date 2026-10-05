<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove all legacy geography columns from all tables
     */
    public function up(): void
    {
        // Helper function to safely drop columns
        $safeDropColumn = function ($tableName, $columns) {
            foreach ((array) $columns as $column) {
                try {
                    if (Schema::hasColumn($tableName, $column)) {
                        Schema::table($tableName, function (Blueprint $table) use ($column) {
                            $table->dropColumn($column);
                        });
                        echo "✓ Removed {$column} from {$tableName}\n";
                    }
                } catch (\Exception $e) {
                    echo "⚠ Skipped {$column} from {$tableName} (may have constraints)\n";
                }
            }
        };

        // Remove columns from users table
        $safeDropColumn('users', ['sector_id', 'zone_id', 'territory_id', 'subsidiary_id', 'region_id']);

        // Remove columns from activities table
        $safeDropColumn('activities', ['sector_id', 'zone_id', 'territory_id', 'subsidiary_id', 'region_id']);

        // Remove columns from documents table
        $safeDropColumn('documents', ['subsidiary_id', 'region_id']);

        // Remove columns from other tables
        $safeDropColumn('expenses', 'subsidiary_id');
        $safeDropColumn('events', 'subsidiary_id');
        $safeDropColumn('sales', 'subsidiary_id');
        $safeDropColumn('orders', 'subsidiary_id');
        $safeDropColumn('opportunities', 'subsidiary_id');
        $safeDropColumn('tasks', 'subsidiary_id');
        $safeDropColumn('indicators', 'subsidiary_id');
        $safeDropColumn('products', 'subsidiary_id');
        $safeDropColumn('settings', 'subsidiary_id');

        echo "✓✓✓ All legacy geography columns removed successfully ✓✓✓\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse: this is a destructive migration
        echo "⚠ Cannot reverse: This migration removes legacy columns\n";
    }
};
