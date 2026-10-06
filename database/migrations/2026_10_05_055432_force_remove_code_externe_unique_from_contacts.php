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
        if (DB::getDriverName() === 'mysql') {
            Schema::table('contacts', function (Blueprint $table) {
                $indexesFound = DB::select("SHOW INDEXES FROM contacts WHERE Key_name = 'contacts_code_externe_unique'");
                if (!empty($indexesFound)) {
                    $table->dropUnique('contacts_code_externe_unique');
                }

                $compositeFound = DB::select("SHOW INDEXES FROM contacts WHERE Key_name = 'contacts_user_id_code_externe_unique'");
                if (empty($compositeFound)) {
                    $table->unique(['user_id', 'code_externe']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
