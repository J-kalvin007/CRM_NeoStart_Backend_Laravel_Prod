<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy geography was removed, but its surviving FK prevents document inserts.
        if (!Schema::hasTable('documents')) return;
        foreach (Schema::getForeignKeys('documents') as $foreign) {
            if (in_array($foreign['foreign_table'], ['subsidiaries', 'organizational_units'], true) && !Schema::hasTable($foreign['foreign_table'])) {
                Schema::table('documents', fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? $foreign['columns']));
            }
        }
    }

    public function down(): void
    {
        // The referenced legacy table no longer exists; do not recreate an invalid constraint.
    }
};
