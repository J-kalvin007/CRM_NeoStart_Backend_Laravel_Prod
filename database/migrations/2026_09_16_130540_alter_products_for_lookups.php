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
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['presentation_galenique']);
            $table->foreignUuid('galenic_form_id')->nullable()->constrained('galenic_forms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['galenic_form_id']);
            $table->dropColumn(['galenic_form_id']);
            $table->string('presentation_galenique')->nullable();
        });
    }
};
