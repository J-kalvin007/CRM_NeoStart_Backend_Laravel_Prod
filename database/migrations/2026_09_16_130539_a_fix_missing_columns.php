<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contacts
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'specialite')) $table->string('specialite')->nullable();
            if (!Schema::hasColumn('contacts', 'segmentation')) $table->string('segmentation')->nullable();
            if (!Schema::hasColumn('contacts', 'potentiel')) $table->string('potentiel')->nullable();
            if (!Schema::hasColumn('contacts', 'influence')) $table->string('influence')->nullable();
        });

        // Accounts
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'potentiel')) $table->string('potentiel')->nullable();
        });

        // Products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'presentation_galenique')) $table->string('presentation_galenique')->nullable();
        });

        // Activities
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'type')) $table->string('type')->nullable();
            if (!Schema::hasColumn('activities', 'resultat')) $table->string('resultat')->nullable();
        });

        // Events
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'format')) $table->string('format')->nullable();
        });
    }

    public function down(): void
    {
        // Down not needed, the subsequent migrations will drop them anyway.
    }
};
