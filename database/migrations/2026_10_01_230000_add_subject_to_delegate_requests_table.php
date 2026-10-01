<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'application envoie un objet et une justification qui n'étaient pas conservés.
     */
    public function up(): void
    {
        Schema::table('delegate_requests', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('request_type');
            $table->text('justification')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('delegate_requests', function (Blueprint $table) {
            $table->dropColumn(['subject', 'justification']);
        });
    }
};
