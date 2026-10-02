<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi GPS des délégués : historique des positions + dernière position connue sur l'utilisateur.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_locations')) {
            Schema::create('user_locations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('user_id')->index();
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->float('accuracy')->nullable();   // précision en mètres
                $table->float('speed')->nullable();      // m/s
                $table->unsignedTinyInteger('battery')->nullable(); // %
                $table->timestamp('recorded_at');        // horodatage côté téléphone
                $table->timestamps();

                $table->index(['user_id', 'recorded_at']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_lat')) {
                $table->decimal('last_lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('users', 'last_lng')) {
                $table->decimal('last_lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('users', 'last_accuracy')) {
                $table->float('last_accuracy')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_lat', 'last_lng', 'last_accuracy', 'last_location_at']);
        });
        Schema::dropIfExists('user_locations');
    }
};
