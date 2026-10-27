<?php

namespace Tests\Feature;

use App\Models\{Account, Activity, Contact, Country, DelegateRequest, DigitalSupport, KolProfile, Product, Role, SampleMovement, SampleQuota, SampleRequest, TourPlan, User};
use App\Models\Scopes\DynamicPerimeterScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Country $togo;
    private Country $benin;
    private User $admin;
    private User $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->togo = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->benin = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['country_id' => $this->togo->id]);
        $this->admin->assignRole('superadmin');
        $this->delegate = User::factory()->create(['country_id' => $this->togo->id]);
        Sanctum::actingAs($this->admin);
    }

    public function test_empty_country_stays_empty_across_operational_endpoints_even_for_a_global_admin(): void
    {
        $account = Account::create(['nom' => 'Établissement Togo', 'country_id' => $this->togo->id, 'user_id' => $this->delegate->id, 'statut_validation' => 'en_attente']);
        $contact = Contact::create(['nom' => 'Praticien Togo', 'prenom' => 'Test', 'user_id' => $this->delegate->id, 'account_id' => $account->id, 'statut_validation' => 'en_attente']);
        $product = Product::create(['nom' => 'Produit Togo', 'code' => 'TG']);
        $product->countries()->attach($this->togo->id, ['stock_actuel' => 80]);
        DigitalSupport::create(['title' => 'Présentation Togo', 'product_id' => $product->id, 'file_path' => 'togo.pdf']);
        KolProfile::create(['contact_id' => $contact->id, 'expertise' => 'Cardiologie']);
        $plan = TourPlan::create(['user_id' => $this->delegate->id, 'nom' => 'Planning Togo', 'date_debut' => now()->toDateString(), 'date_fin' => now()->addDays(6)->toDateString(), 'statut' => 'soumis']);
        foreach (['visite', 'conge'] as $type) {
            Activity::create(['user_id' => $this->delegate->id, 'activity_type' => $type, 'date_activite' => now()->toDateString(), 'statut' => 'en_attente', 'tour_plan_id' => $plan->id]);
        }
        DelegateRequest::create(['user_id' => $this->delegate->id, 'request_type' => 'general', 'subject' => 'Demande Togo', 'priority' => 'normal', 'status' => 'pending_manager_approval']);
        SampleRequest::create(['user_id' => $this->delegate->id, 'product_id' => $product->id, 'quantity' => 5, 'status' => 'pending']);
        SampleMovement::create(['user_id' => $this->admin->id, 'product_id' => $product->id, 'movement_type' => 'reception', 'quantity' => 10]);

        foreach (['accounts', 'contacts', 'products', 'activities', 'leaves', 'tour-plans', 'validations/pending', 'digital-supports', 'kol-profiles', 'sample-requests'] as $endpoint) {
            $response = $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/'.$endpoint)->assertOk();
            $this->assertSame([], ($response->json('data') ?? $response->json()), $endpoint.' leaked Togo records into Benin');
        }
        foreach (['requests', 'my-stock'] as $endpoint) {
            $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/'.$endpoint)->assertOk()->assertExactJson([]);
        }
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/analytics/executive')->assertOk()->assertJsonPath('total_visits', 0);
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/leaves')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeader('X-Country-Id', 'group')->getJson('/api/v1/leaves')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_product_stock_and_prices_use_the_selected_country_even_with_iso_codes(): void
    {
        $product = Product::create(['nom' => 'Produit partagé', 'code' => 'SHARED']);
        $product->countries()->attach($this->togo->id, ['stock_actuel' => 100, 'ppv' => 1500]);
        $product->countries()->attach($this->benin->id, ['stock_actuel' => 3, 'ppv' => 900]);
        foreach ([$this->benin->id, 'BJ', 'Bénin'] as $selection) {
            $response = $this->withHeader('X-Country-Id', $selection)->getJson('/api/v1/products')->assertOk();
            $this->assertSame(3, (int) $response->json('data.0.stock_actuel'));
            $this->assertSame(900, (int) $response->json('data.0.ppv'));
            $response->assertJsonCount(1, 'data.0.countries')->assertJsonPath('data.0.countries.0.id', $this->benin->id);
        }
    }

    public function test_detail_routes_do_not_leak_a_record_from_another_selected_country(): void
    {
        $account = Account::create(['nom' => 'Clinique Togo', 'country_id' => $this->togo->id, 'user_id' => $this->delegate->id]);
        $contact = Contact::create(['nom' => 'Praticien Togo', 'prenom' => 'Test', 'user_id' => $this->delegate->id]);
        $plan = TourPlan::create(['user_id' => $this->delegate->id, 'nom' => 'Togo', 'date_debut' => now()->toDateString(), 'date_fin' => now()->toDateString(), 'statut' => 'soumis']);
        foreach (['accounts/'.$account->id, 'contacts/'.$contact->id, 'tour-plans/'.$plan->id] as $endpoint) {
            $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/'.$endpoint)->assertNotFound();
        }
    }

    public function test_country_scope_survives_hierarchy_bypass_and_model_saving_hooks(): void
    {
        Account::create(['nom' => 'Togo', 'country_id' => $this->togo->id, 'user_id' => $this->delegate->id]);
        $other = User::factory()->create(['country_id' => $this->benin->id]);
        $benin = Account::create(['nom' => 'Bénin', 'country_id' => $this->benin->id, 'user_id' => $other->id]);
        $this->app['request']->headers->set('X-Country-Id', $this->benin->id);
        $this->assertSame([$benin->id], Account::withoutGlobalScope(DynamicPerimeterScope::class)->pluck('id')->all());
        $this->assertSame([$benin->id], Account::query()->pluck('id')->all());
    }

    public function test_country_query_aliases_and_unknown_country_never_fall_back_to_global_data(): void
    {
        Account::create(['nom' => 'Togo', 'country_id' => $this->togo->id, 'user_id' => $this->delegate->id]);
        foreach (['country_id', 'country', 'pays'] as $parameter) {
            $this->getJson('/api/v1/accounts?'.$parameter.'='.$this->benin->id)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->withHeader('X-Country-Id', 'unknown-country')->getJson('/api/v1/accounts')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeader('X-Country-Id', 'group')->getJson('/api/v1/accounts')->assertOk()->assertJsonCount(1, 'data');
    }
}
