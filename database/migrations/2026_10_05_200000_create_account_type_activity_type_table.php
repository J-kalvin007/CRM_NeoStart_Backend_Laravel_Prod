<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot pour associer les types d'activités aux types d'établissements.
     */
    public function up(): void
    {
        Schema::create('account_type_activity_type', function (Blueprint $table) {
            $table->foreignUuid('account_type_id')->constrained('account_types')->cascadeOnDelete();
            $table->foreignUuid('activity_type_id')->constrained('activity_types')->cascadeOnDelete();
            $table->primary(['account_type_id', 'activity_type_id'], 'acc_act_type_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_type_activity_type');
    }
};
