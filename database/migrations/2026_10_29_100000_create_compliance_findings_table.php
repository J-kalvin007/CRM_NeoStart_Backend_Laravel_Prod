<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 64)->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->string('type');
            $table->string('severity');
            $table->string('status')->default('EN_ATTENTE');
            $table->string('description');
            $table->json('evidence');
            $table->timestamp('detected_at');
            $table->timestamps();
        });
        Schema::create('compliance_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('finding_id')->constrained('compliance_findings')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users');
            $table->string('action');
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_decisions');
        Schema::dropIfExists('compliance_findings');
    }
};
