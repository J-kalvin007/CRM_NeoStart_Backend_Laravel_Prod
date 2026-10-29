<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'created_by')) {
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'country_id')) {
                $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            }
        });
        if (! Schema::hasTable('message_reads')) {
            Schema::create('message_reads', function (Blueprint $table) {
                $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->primary(['message_id', 'user_id']);
            });
        }
        if (! Schema::hasTable('message_attachments')) {
            Schema::create('message_attachments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('path');
                $table->string('name');
                $table->string('mime');
                $table->unsignedBigInteger('size');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_reads');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
