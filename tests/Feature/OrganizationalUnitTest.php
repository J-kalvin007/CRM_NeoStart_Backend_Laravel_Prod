<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationalUnitTest extends TestCase
{
    public function test_organizational_unit_model_and_table_exist(): void
    {
        Artisan::call('migrate:fresh');

        $this->assertTrue(class_exists(\App\Models\OrganizationalUnit::class));
        $this->assertTrue(Schema::hasTable('organizational_units'));
    }
}
