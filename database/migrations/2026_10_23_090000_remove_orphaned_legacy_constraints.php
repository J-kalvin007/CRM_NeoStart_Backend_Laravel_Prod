<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $retired = ['subsidiaries', 'organizational_units', 'regions', 'territories', 'zones', 'sectors', 'teams'];
        foreach (Schema::getTableListing() as $tableName) {
            $obsolete = array_filter(Schema::getForeignKeys($tableName), fn ($foreign) =>
                in_array($foreign['foreign_table'], $retired, true) && !Schema::hasTable($foreign['foreign_table']));
            if ($obsolete) {
                Schema::table($tableName, function (Blueprint $table) use ($obsolete) {
                    foreach ($obsolete as $foreign) $table->dropForeign($foreign['name'] ?? $foreign['columns']);
                });
            }
            // Obsolete IDs must no longer be mandatory on new operational records.
            foreach (['subsidiary_id', 'organizational_unit_id'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->uuid($column)->nullable()->change());
                }
            }
        }
    }

    public function down(): void
    {
        // Retired tables are absent; restoring these constraints would break writes.
    }
};
