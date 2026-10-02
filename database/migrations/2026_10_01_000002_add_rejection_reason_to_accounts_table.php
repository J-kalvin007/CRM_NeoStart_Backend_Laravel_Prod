<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('accounts', 'rejection_reason')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('rejection_reason')->nullable()->after('statut_validation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'rejection_reason')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
