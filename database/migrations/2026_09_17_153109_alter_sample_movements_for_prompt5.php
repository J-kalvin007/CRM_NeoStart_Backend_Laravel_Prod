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
        Schema::table('sample_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('sample_movements', 'contact_id')) {
                $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete()->after('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sample_movements', function (Blueprint $table) {
            if (Schema::hasColumn('sample_movements', 'contact_id')) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            }
        });
    }
};
