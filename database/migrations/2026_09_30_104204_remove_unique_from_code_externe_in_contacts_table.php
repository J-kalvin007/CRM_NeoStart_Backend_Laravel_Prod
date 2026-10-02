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
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['code_externe']);
            // Add a composite unique index on user_id and code_externe to prevent duplicate imports for the SAME delegate
            $table->unique(['user_id', 'code_externe']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'code_externe']);
            // Wait, what if there are duplicates now? Re-adding unique might fail, but that's standard for down methods.
            $table->unique('code_externe');
        });
    }
};
