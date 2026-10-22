<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->date('date_fin')->nullable();
            $table->boolean('outside_weekly_plan')->default(false);
            $table->index(['user_id', 'outside_weekly_plan', 'date_activite'], 'activities_outside_weekly_index');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_outside_weekly_index');
            $table->dropColumn(['date_fin', 'outside_weekly_plan']);
        });
    }
};
