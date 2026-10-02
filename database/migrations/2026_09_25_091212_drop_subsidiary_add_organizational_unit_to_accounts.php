<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['subsidiary_id']);
            $table->dropColumn('subsidiary_id');
            $table->foreignUuid('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
            $table->dropColumn('organizational_unit_id');
            $table->foreignUuid('subsidiary_id')->nullable()->constrained('subsidiaries')->nullOnDelete();
        });
    }
};
