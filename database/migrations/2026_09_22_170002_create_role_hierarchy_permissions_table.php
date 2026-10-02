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
        Schema::create('role_hierarchy_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->uuid('organization_config_id');
            $table->json('allowed_level_codes'); // ["region", "prefecture", "commune"]
            $table->json('can_manage_level_codes'); // ["commune", "zone"]
            $table->enum('access_scope', ['own_perimeter', 'team_perimeter', 'global'])->default('own_perimeter');
            $table->boolean('can_create_units')->default(false);
            $table->boolean('can_edit_units')->default(false);
            $table->boolean('can_delete_units')->default(false);
            $table->boolean('can_assign_users')->default(false);
            $table->json('restrictions')->nullable(); // Restrictions spécifiques
            $table->timestamps();

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->foreign('organization_config_id')
                  ->references('id')
                  ->on('organization_configs')
                  ->onDelete('cascade');

            $table->unique(['role_id', 'organization_config_id']);
            $table->index(['role_id', 'access_scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_hierarchy_permissions');
    }
};