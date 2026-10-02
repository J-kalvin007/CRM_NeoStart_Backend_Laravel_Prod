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
        if (!Schema::hasTable('activity_contact')) {
            Schema::create('activity_contact', function (Blueprint $table) {
                $table->string('activity_id')->index();
                $table->string('contact_id')->index();
                $table->timestamps();

                $table->primary(['activity_id', 'contact_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_contact');
    }
};
