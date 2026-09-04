<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->json('validation_rules')->nullable()->after('is_active')
                ->comment('Règles de validation Laravel pour le champ meta (ex: {"finess":"required|size:9"})');
        });

        Schema::table('contact_types', function (Blueprint $table) {
            $table->json('validation_rules')->nullable()->after('is_active')
                ->comment('Règles de validation Laravel pour le champ meta (ex: {"rpps":"required|size:11"})');
        });
    }

    public function down(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->dropColumn('validation_rules');
        });

        Schema::table('contact_types', function (Blueprint $table) {
            $table->dropColumn('validation_rules');
        });
    }
};
