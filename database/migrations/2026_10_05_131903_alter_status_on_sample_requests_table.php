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
        Schema::table('sample_requests', function (Blueprint $table) {
            // Because MySQL ENUM alteration can be tricky, we change the column type to string for simplicity.
            $table->string('status')->default('BROUILLON')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sample_requests', function (Blueprint $table) {
            $table->enum('status', ['pending','approved','rejected'])->default('pending')->change();
        });
    }
};
