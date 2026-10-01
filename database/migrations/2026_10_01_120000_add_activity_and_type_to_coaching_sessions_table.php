<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lie une session de duo à la visite accompagnée et précise sa nature (formation | coaching).
     */
    public function up(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->foreignUuid('activity_id')->nullable()->after('delegate_id')->constrained('activities')->nullOnDelete();
            $table->string('type')->nullable()->after('activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn(['activity_id', 'type']);
        });
    }
};
