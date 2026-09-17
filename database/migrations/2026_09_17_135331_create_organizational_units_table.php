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
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type'); // 'group', 'country', 'subsidiary', 'region', 'territory', 'zone', 'sector', 'team'
            $table->uuid('parent_id')->nullable();
            $table->json('metadata')->nullable(); // For additional data like code, currency, description
            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('organizational_units')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
