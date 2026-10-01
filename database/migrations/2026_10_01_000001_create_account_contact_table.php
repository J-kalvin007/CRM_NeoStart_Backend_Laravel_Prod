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
        if (!Schema::hasTable('account_contact')) {
            Schema::create('account_contact', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('contact_id');
                $table->boolean('is_primary')->default(true);
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
                $table->unique(['account_id', 'contact_id']);
            });
        }

        if (!Schema::hasColumn('contacts', 'rejection_reason')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('rejection_reason')->nullable()->after('statut_validation');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_contact');
        if (Schema::hasColumn('contacts', 'rejection_reason')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
