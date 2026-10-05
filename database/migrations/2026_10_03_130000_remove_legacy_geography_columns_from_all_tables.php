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
        // Remove columns from users table
        Schema::table('users', function (Blueprint $table) {
            $columns = ['sector_id', 'zone_id', 'territory_id', 'subsidiary_id', 'region_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                    echo "✓ Removed {$column} from users\n";
                }
            }
        });

        // Remove columns from activities table
        Schema::table('activities', function (Blueprint $table) {
            $columns = ['sector_id', 'zone_id', 'territory_id', 'subsidiary_id', 'region_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('activities', $column)) {
                    $table->dropColumn($column);
                    echo "✓ Removed {$column} from activities\n";
                }
            }
        });

        // Remove columns from documents table
        Schema::table('documents', function (Blueprint $table) {
            $columns = ['subsidiary_id', 'region_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                    echo "✓ Removed {$column} from documents\n";
                }
            }
        });

        // Remove columns from expenses table
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from expenses\n";
            }
        });

        // Remove columns from events table
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from events\n";
            }
        });

        // Remove columns from sales table
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from sales\n";
            }
        });

        // Remove columns from orders table
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from orders\n";
            }
        });

        // Remove columns from opportunities table
        Schema::table('opportunities', function (Blueprint $table) {
            if (Schema::hasColumn('opportunities', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from opportunities\n";
            }
        });

        // Remove columns from tasks table
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from tasks\n";
            }
        });

        // Remove columns from indicators table
        Schema::table('indicators', function (Blueprint $table) {
            if (Schema::hasColumn('indicators', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from indicators\n";
            }
        });

        // Remove columns from products table
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from products\n";
            }
        });

        // Remove columns from settings table
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'subsidiary_id')) {
                $table->dropColumn('subsidiary_id');
                echo "✓ Removed subsidiary_id from settings\n";
            }
        });

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
