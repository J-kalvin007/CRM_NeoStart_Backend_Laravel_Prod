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
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Périmètre et Appartenance
            $table->foreignUuid('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // Le délégué qui soumet
            
            // Détails de la dépense
            $table->string('title');
            $table->enum('type', ['travel', 'meal', 'accommodation', 'material', 'other'])->default('other');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->date('expense_date');
            
            // Lien Polymorphique (La dépense peut être liée à une Visite(Activity) ou un Congrès(Event))
            $table->uuid('expensable_id')->nullable();
            $table->string('expensable_type', 100)->nullable();
            $table->index(['expensable_id', 'expensable_type']);
            
            // Workflow d'approbation
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Le manager qui valide
            $table->text('rejection_reason')->nullable();
            
            // Justificatif (Photo du reçu)
            $table->string('receipt_file_path')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
