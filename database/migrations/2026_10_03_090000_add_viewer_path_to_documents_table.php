<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Version affichable du fichier (PDF converti d'un PowerPoint, index.html d'un paquet HTML5)
            $table->string('viewer_path')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('viewer_path');
        });
    }
};
