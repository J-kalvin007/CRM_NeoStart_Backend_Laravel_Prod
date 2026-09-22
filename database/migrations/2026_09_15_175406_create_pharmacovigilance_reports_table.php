<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacovigilance_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('patient_initials');
            $table->integer('patient_age')->nullable();
            $table->enum('patient_gender', ['M', 'F', 'Autre'])->nullable();
            $table->text('adverse_event_description');
            $table->string('severity')->default('moderee');
            $table->date('report_date');
            $table->string('status')->default('soumis');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacovigilance_reports');
    }
};
