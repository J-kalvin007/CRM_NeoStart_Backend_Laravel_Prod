<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop prix_unitaire if it exists
            if (Schema::hasColumn('products', 'prix_unitaire')) {
                $table->dropColumn('prix_unitaire');
            }

            // Add foreign keys conditionally
            if (!Schema::hasColumn('products', 'laboratory_id')) {
                $table->foreignUuid('laboratory_id')->nullable()->constrained('laboratories')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'therapeutic_class_id')) {
                $table->foreignUuid('therapeutic_class_id')->nullable()->constrained('therapeutic_classes')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'galenic_form_id')) {
                $table->foreignUuid('galenic_form_id')->nullable()->constrained('galenic_forms')->nullOnDelete();
            } else {
                // If the column exists, it might not have the foreign key constraint yet.
                // We could add it, but for safety in this migration context we just ensure it's there.
            }

            // Add new pharma fields conditionally
            if (!Schema::hasColumn('products', 'dci')) {
                $table->string('dci')->nullable();
                $table->string('conditionnement')->nullable();
                $table->text('indications')->nullable();
                $table->text('posologie')->nullable();
                $table->text('effets_secondaires')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'prix_unitaire')) {
                $table->decimal('prix_unitaire', 10, 2)->nullable();
            }
            
            // Drop columns and foreign keys (only safe way is to wrap in try catch or assume existence)
            // For simplicity in rollback, we drop the newly added columns
            $table->dropForeign(['laboratory_id']);
            $table->dropForeign(['therapeutic_class_id']);
            // $table->dropForeign(['galenic_form_id']); // Might have been there before

            $table->dropColumn([
                'laboratory_id', 
                'therapeutic_class_id', 
                'dci',
                'conditionnement',
                'indications',
                'posologie',
                'effets_secondaires'
            ]);
        });
    }
};
