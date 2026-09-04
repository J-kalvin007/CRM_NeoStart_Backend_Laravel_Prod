<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['territory_id']);
            $table->dropColumn('territory_id');
            $table->foreignUuid('sector_id')->after('id')->nullable()->constrained('sectors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropColumn('sector_id');
            $table->foreignUuid('territory_id')->after('id')->nullable()->constrained('territories')->cascadeOnDelete();
        });
    }
};
