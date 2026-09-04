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
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Propriétaire (Délégué) et Périmètre (Multi-tenant)
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('territory_id')->nullable()->constrained()->nullOnDelete();
            
            // Cible optionnelle (Médecin ou Établissement)
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained()->nullOnDelete();
            
            // Détails de la tâche
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['visit', 'call', 'email', 'meeting', 'other'])->default('visit');
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            
            // Planification
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
