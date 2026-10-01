<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Une session DUO peut être refusée (par le délégué ou le superviseur).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE coaching_sessions MODIFY status ENUM('draft','active','completed','refused') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE coaching_sessions SET status = 'draft' WHERE status = 'refused'");
            DB::statement("ALTER TABLE coaching_sessions MODIFY status ENUM('draft','active','completed') NOT NULL DEFAULT 'draft'");
        }
    }
};
