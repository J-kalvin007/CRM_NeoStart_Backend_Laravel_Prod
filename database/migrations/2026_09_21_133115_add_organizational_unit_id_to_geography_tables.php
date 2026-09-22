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
        $tables = ['regions', 'territories', 'zones', 'sectors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (!Schema::hasColumn($t->getTable(), 'organizational_unit_id')) {
                    $t->foreignUuid('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['regions', 'territories', 'zones', 'sectors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (Schema::hasColumn($t->getTable(), 'organizational_unit_id')) {
                    $t->dropForeign(['organizational_unit_id']);
                    $t->dropColumn('organizational_unit_id');
                }
            });
        }
    }
};
