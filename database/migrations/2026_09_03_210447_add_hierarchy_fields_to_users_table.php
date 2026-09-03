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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->uuid('country_id')->nullable()->after('remember_token');
            $table->uuid('subsidiary_id')->nullable()->after('country_id');
            $table->uuid('team_id')->nullable()->after('subsidiary_id');
            $table->uuid('manager_id')->nullable()->after('team_id');
            $table->boolean('status')->default(true)->after('manager_id');
            $table->timestamp('last_login_at')->nullable()->after('status');
            
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('subsidiary_id')->references('id')->on('subsidiaries')->nullOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['subsidiary_id']);
            $table->dropForeign(['team_id']);
            $table->dropForeign(['manager_id']);
            
            $table->dropColumn([
                'phone', 
                'country_id', 
                'subsidiary_id', 
                'team_id', 
                'manager_id', 
                'status', 
                'last_login_at'
            ]);
        });
    }
};
