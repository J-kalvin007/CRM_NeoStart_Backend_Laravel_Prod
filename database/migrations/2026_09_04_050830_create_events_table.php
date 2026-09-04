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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre (Qui organise)
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            
            // Détails de l'événement
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['congress', 'webinar', 'training', 'symposium', 'other'])->default('congress');
            $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
            
            // Logistique
            $table->string('location')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
