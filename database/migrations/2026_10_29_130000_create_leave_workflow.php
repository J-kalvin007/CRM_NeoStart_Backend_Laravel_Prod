<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'leaves.approve-final', 'guard_name' => 'web'], ['module' => 'Congés & Permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('manager_fonctionnel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('maternity_leave_eligible')->default(false);
        });
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->unique()->constrained('countries');
            $table->json('permission_rules');
            $table->json('absence_codes')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type', 30);
            $table->decimal('initial_days', 8, 2)->default(0);
            $table->decimal('remaining_days', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'year', 'leave_type']);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->unique()->constrained('activities');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('leave_type', 30);
            $table->string('permission_code')->nullable();
            $table->string('type_label');
            $table->date('return_date');
            $table->unsignedSmallInteger('reference_year');
            $table->string('absence_code')->nullable();
            $table->foreignUuid('interim_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('days', 8, 2);
            $table->string('grant_status', 30)->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamp('debited_at')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->unsignedInteger('step');
            $table->string('action', 50);
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['leave_request_id', 'step']);
        });
        Schema::create('leave_balance_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('leave_balance_id')->constrained('leave_balances');
            $table->foreignUuid('leave_request_id')->nullable()->unique()->constrained('leave_requests');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->decimal('days', 8, 2);
            $table->decimal('remaining_after', 8, 2);
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('leave_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    // The permission catalog is retained on rollback to preserve explicit role assignments.
    public function down(): void
    {
        foreach (['leave_documents', 'leave_balance_entries', 'leave_workflow_steps', 'leave_requests', 'leave_balances', 'leave_policies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_fonctionnel_id');
            $table->dropColumn('maternity_leave_eligible');
        });
    }
};
