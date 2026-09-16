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
            $table->dropColumn(['specialite', 'segmentation', 'potentiel', 'influence']);
            $table->foreignUuid('speciality_id')->nullable()->constrained('specialities')->nullOnDelete();
            $table->foreignUuid('segmentation_id')->nullable()->constrained('segmentations')->nullOnDelete();
            $table->foreignUuid('potential_id')->nullable()->constrained('potentials')->nullOnDelete();
            $table->foreignUuid('influence_id')->nullable()->constrained('influences')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['speciality_id']);
            $table->dropForeign(['segmentation_id']);
            $table->dropForeign(['potential_id']);
            $table->dropForeign(['influence_id']);
            $table->dropColumn(['speciality_id', 'segmentation_id', 'potential_id', 'influence_id']);
            $table->string('specialite')->nullable();
            $table->string('segmentation')->nullable();
            $table->string('potentiel')->nullable();
            $table->string('influence')->nullable();
        });
    }
};
