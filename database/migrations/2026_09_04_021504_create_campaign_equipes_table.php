<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_team', function (Blueprint $table) {
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            
            $table->primary(['campaign_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_team');
    }
};
