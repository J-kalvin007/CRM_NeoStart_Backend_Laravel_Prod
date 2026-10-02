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
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignUuid('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignUuid('speciality_id')->nullable()->constrained('specialities')->nullOnDelete();
            $table->foreignUuid('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->enum('status', ['draft', 'review', 'approved', 'expired'])->default('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
            $table->dropForeign(['speciality_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['organizational_unit_id', 'speciality_id', 'role_id', 'status']);
        });
    }
};
