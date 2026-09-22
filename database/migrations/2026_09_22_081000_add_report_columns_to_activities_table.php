<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'report_status')) {
                $table->string('report_status')->nullable()->default(null)->after('statut');
            }
            if (!Schema::hasColumn('activities', 'report_content')) {
                $table->longText('report_content')->nullable()->after('report_status');
            }
            if (!Schema::hasColumn('activities', 'validated_by_id')) {
                $table->foreignUuid('validated_by_id')->nullable()->constrained('users')->nullOnDelete()->after('report_content');
            }
            if (!Schema::hasColumn('activities', 'validation_date')) {
                $table->dateTime('validation_date')->nullable()->after('validated_by_id');
            }
            if (!Schema::hasColumn('activities', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('validation_date');
            }
            if (!Schema::hasColumn('activities', 'signature')) {
                $table->longText('signature')->nullable()->after('rejection_reason');
            }
        });

        try {
            DB::statement("ALTER TABLE activities MODIFY COLUMN statut ENUM('planifiee', 'validee', 'en_cours', 'realisee', 'manquee', 'annulee') NOT NULL DEFAULT 'planifiee'");
        } catch (\Throwable $e) {
            // If already altered or sqlite in testing
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'report_status')) {
                $table->dropColumn('report_status');
            }
            if (Schema::hasColumn('activities', 'report_content')) {
                $table->dropColumn('report_content');
            }
            if (Schema::hasColumn('activities', 'validated_by_id')) {
                $table->dropForeign(['validated_by_id']);
                $table->dropColumn('validated_by_id');
            }
            if (Schema::hasColumn('activities', 'validation_date')) {
                $table->dropColumn('validation_date');
            }
            if (Schema::hasColumn('activities', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('activities', 'signature')) {
                $table->dropColumn('signature');
            }
        });
    }
};
