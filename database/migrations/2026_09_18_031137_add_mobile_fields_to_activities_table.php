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
        Schema::table('activities', function (Blueprint $table) {
            $table->longText('signature_base64')->nullable()->after('report_content');
            $table->integer('niveau_interet')->nullable()->after('signature_base64');
            $table->string('motif')->nullable()->after('activity_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['signature_base64', 'niveau_interet', 'motif']);
        });
    }
};
