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
        Schema::create('indicators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->string('name')->comment('Nom de l\'indicateur, ex: Nombre de visites, CA Généré');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            
            // Comment l'indicateur est-il calculé ? (sum, count, average, manual)
            $table->string('calculation_type')->default('manual'); 
            
            // Sur quel modèle se base le calcul ? (ex: App\Models\Activity)
            $table->string('data_source_model')->nullable(); 
            
            // Sur quel champ appliquer l'opération ? (ex: pour somme du CA, 'expected_revenue')
            $table->string('data_source_field')->nullable();
            
            $table->string('unit')->nullable()->comment('Unité de mesure (FCFA, Visites, etc.)');
            $table->boolean('is_active')->default(true);

            $table->foreignUuid('subsidiary_id')->nullable()->constrained('subsidiaries')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
