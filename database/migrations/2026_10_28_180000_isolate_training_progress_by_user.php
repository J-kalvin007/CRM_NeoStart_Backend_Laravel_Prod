<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->text('file_url')->nullable();
        });
        Schema::create('training_user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('progress', 5, 2)->default(0);
            $table->string('status')->default('A_FAIRE');
            $table->timestamps();
            $table->unique(['training_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_user_progress');
        Schema::table('trainings', fn (Blueprint $table) => $table->dropColumn(['category', 'file_url']));
    }
};
