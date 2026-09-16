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
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['type', 'resultat']);
            $table->foreignUuid('activity_type_id')->nullable()->constrained('activity_types')->nullOnDelete();
            $table->foreignUuid('activity_result_id')->nullable()->constrained('activity_results')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['activity_type_id']);
            $table->dropForeign(['activity_result_id']);
            $table->dropColumn(['activity_type_id', 'activity_result_id']);
            $table->string('type')->nullable();
            $table->string('resultat')->nullable();
        });
    }
};
