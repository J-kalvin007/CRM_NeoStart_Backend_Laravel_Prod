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
            // Duo/Trio columns - add only if they don't exist
            if (!Schema::hasColumn('activities', 'duo_user_id')) {
                $table->uuid('duo_user_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('activities', 'duo_activity_type_id')) {
                $table->uuid('duo_activity_type_id')->nullable()->after('duo_user_id');
            }
            if (!Schema::hasColumn('activities', 'duo_status')) {
                $table->string('duo_status')->nullable()->after('duo_activity_type_id');
            }
            if (!Schema::hasColumn('activities', 'duo_delegue_status')) {
                $table->string('duo_delegue_status')->nullable()->after('duo_status');
            }
            if (!Schema::hasColumn('activities', 'duo_delegue_rejection_reason')) {
                $table->text('duo_delegue_rejection_reason')->nullable()->after('duo_delegue_status');
            }
            // accompanying_user_id already exists
            if (!Schema::hasColumn('activities', 'is_duo')) {
                $table->boolean('is_duo')->default(false)->after('accompanying_user_id');
            }
            if (!Schema::hasColumn('activities', 'is_trio')) {
                $table->boolean('is_trio')->default(false)->after('is_duo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $columnsToDrop = [
                'duo_user_id',
                'duo_activity_type_id',
                'duo_status',
                'duo_delegue_status',
                'duo_delegue_rejection_reason',
                'is_duo',
                'is_trio',
            ];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
