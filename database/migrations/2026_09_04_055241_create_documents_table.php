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
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre
            $table->foreignUuid('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            
            // Polymorphisme (Peut être lié à un Produit, un Compte, une Opportunité, etc.)
            $table->uuid('documentable_id');
            $table->string('documentable_type');
            $table->index(['documentable_id', 'documentable_type']);
            
            // Détails du document
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['pdf', 'video', 'presentation', 'image', 'other'])->default('pdf');
            
            // Fichier et versioning
            $table->string('file_path'); 
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->date('expiration_date')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
