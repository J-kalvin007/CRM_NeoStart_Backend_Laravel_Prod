<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationalUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_organization_groups_users_by_country_without_legacy_units(): void
    {
        $this->assertFalse(Schema::hasTable('organizational_units'));
        $this->assertTrue(Schema::hasColumns('users', ['country_id', 'manager_id']));
        $country = Country::create(['name' => 'Togo', 'iso_code' => 'TG', 'currency' => 'XOF']);
        $user = User::factory()->create(['country_id' => $country->id]);
        $this->assertTrue($user->country->is($country));
    }
}
