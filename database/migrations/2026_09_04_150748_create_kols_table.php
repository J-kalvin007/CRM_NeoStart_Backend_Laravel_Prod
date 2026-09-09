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
        Schema::create('kols', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('institution')->nullable();
            $table->integer('influence_score')->default(0);
            $table->string('relationship_status')->default('prospect');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kols');
    }
};
