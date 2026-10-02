<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'report_status')) {
                $table->enum('report_status', ['draft', 'submitted', 'validated', 'rejected', 'modified'])->default('draft')->after('statut');
                $table->longText('report_content')->nullable()->after('report_status');
                $table->uuid('validated_by_id')->nullable()->after('report_content');
                $table->timestamp('validation_date')->nullable()->after('validated_by_id');
                $table->text('rejection_reason')->nullable()->after('validation_date');
                
                // Foreign key pour validated_by_id (User)
                $table->foreign('validated_by_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('activity_document')) {
            Schema::create('activity_document', function (Blueprint $table) {
                $table->id();
                $table->uuid('activity_id');
                $table->uuid('document_id');
                $table->integer('duration_seconds')->nullable();
                $table->integer('display_order')->default(0);
                $table->timestamps();

                $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
                $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('activity_document');
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['validated_by_id']);
            $table->dropColumn([
                'report_status', 
                'report_content', 
                'validated_by_id', 
                'validation_date', 
                'rejection_reason'
            ]);
        });
    }
};
