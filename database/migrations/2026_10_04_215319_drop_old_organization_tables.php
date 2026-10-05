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
        Schema::dropIfExists('organizational_unit_product');
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('hierarchy_level_definitions');
        Schema::dropIfExists('organization_configs');
    }

    public function down(): void
    {
        // No down method needed, this is permanent cleanup
    }
};
