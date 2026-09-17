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
        Schema::create('coaching_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supervisor_id')->constrained('users');
            $table->foreignUuid('delegate_id')->constrained('users');
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('next_review_date')->nullable();
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coaching_sessions');
    }
};
