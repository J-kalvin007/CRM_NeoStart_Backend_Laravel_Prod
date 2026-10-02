<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La nature du DUO (coaching | formation) est portée par activities.duo_activity_type_id.
     */
    public function up(): void
    {
        if (Schema::hasColumn('coaching_sessions', 'type')) {
            Schema::table('coaching_sessions', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('activity_id');
        });
    }
};
