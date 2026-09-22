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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('activity_objections', function (Blueprint $table) {
                if (Schema::hasColumn('activity_objections', 'id')) {
                    $table->dropColumn('id');
                }
            });

            Schema::table('activity_document', function (Blueprint $table) {
                if (Schema::hasColumn('activity_document', 'id')) {
                    $table->dropColumn('id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_pivots_tables', function (Blueprint $table) {
            //
        });
    }
};
