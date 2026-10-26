<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Activity;
use App\Models\CoachingSession;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Product;
use App\Models\Role;
use App\Models\Speciality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Country $togo;
    private Country $benin;
    private User $admin;
    private User $delegate;
    private User $other;
    private Account $clinic;
    private Account $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->togo = Country::create(['name' => 'Togo', 'iso_code' => 'TG']);
        $this->benin = Country::create(['name' => 'Bénin', 'iso_code' => 'BJ']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
        $this->delegate = User::factory()->create(['country_id' => $this->togo->id]);
        $this->other = User::factory()->create(['country_id' => $this->benin->id]);
        $this->delegate->assignRole('delegue_medical');
        $this->other->assignRole('delegue_medical');
        $clinicType = AccountType::create(['code' => 'CLI', 'libelle' => 'Clinique']);
        $pharmacyType = AccountType::create(['code' => 'PHA', 'libelle' => 'Pharmacie']);
        $this->clinic = Account::create(['nom' => 'Du Golfe', 'account_type_id' => $clinicType->id,
            'country_id' => $this->togo->id, 'user_id' => $this->delegate->id, 'statut' => 'actif']);
        $this->pharmacy = Account::create(['nom' => 'Cotonou', 'account_type_id' => $pharmacyType->id,
            'country_id' => $this->benin->id, 'user_id' => $this->other->id, 'statut' => 'actif']);
        Sanctum::actingAs($this->admin);
    }

    public function test_account_type_and_country_filters_use_real_columns_and_return_the_raw_name(): void
    {
        foreach (['clinique', 'CLI', $this->clinic->account_type_id] as $type) {
            $this->getJson('/api/v1/accounts?account_type=' . $type)
                ->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $this->clinic->id)
                ->assertJsonPath('data.0.nom_structure', 'Du Golfe');
        }
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/accounts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->pharmacy->id);
        $this->withHeader('X-Country-Id', 'group')->getJson('/api/v1/accounts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_contact_filters_handle_speciality_names_search_kol_and_pivot_establishments(): void
    {
        $speciality = Speciality::create(['name' => 'Cardiologie', 'code' => 'CARDIO']);
        $contact = Contact::create(['nom' => 'Mensah', 'prenom' => 'Afi', 'speciality_id' => $speciality->id,
            'user_id' => $this->delegate->id, 'meta' => ['is_kol' => true], 'statut' => 'actif']);
        $contact->accounts()->attach($this->clinic->id, ['id' => (string) \Illuminate\Support\Str::uuid()]);
        Contact::create(['nom' => 'Autre', 'prenom' => 'Benoît', 'user_id' => $this->other->id, 'statut' => 'actif']);
        foreach (['specialite=Cardiologie', 'specialite=' . $speciality->id, 'search=Cardiologie',
            'is_kol=true', 'account_id=' . $this->clinic->id] as $filter) {
            $this->getJson('/api/v1/contacts?' . $filter)->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $contact->id);
        }
        $this->getJson('/api/v1/contacts?is_kol=false')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/contacts')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $contact->id);
    }

    public function test_activity_lists_keep_pagination_metadata_and_apply_country_and_delegate_filters(): void
    {
        foreach ([$this->delegate, $this->other] as $user) {
            Activity::create(['activity_type' => 'visite', 'user_id' => $user->id,
                'date_activite' => now()->toDateString(), 'statut' => 'planifiee']);
        }
        $this->getJson('/api/v1/activities?per_page=1')->assertOk()
            ->assertJsonPath('current_page', 1)->assertJsonPath('last_page', 2)->assertJsonCount(1, 'data');
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/activities')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $this->delegate->id);
        $this->withHeader('X-Country-Id', 'group')->getJson('/api/v1/activities?delegate_id=' . $this->other->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $this->other->id);
    }

    public function test_country_selection_does_not_broaden_delegate_access(): void
    {
        Sanctum::actingAs($this->delegate);
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/accounts')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unassigned_country_uses_the_owner_country_without_showing_other_countries(): void
    {
        $account = Account::create(['nom' => 'Cabinet rattaché au délégué', 'user_id' => $this->delegate->id, 'statut' => 'actif']);
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/accounts')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $account->id]);
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/accounts')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->pharmacy->id);
    }

    public function test_products_do_not_fall_back_to_all_countries_when_selected_country_has_no_products(): void
    {
        $product = Product::create(['nom' => 'Produit Togo', 'code' => 'TG-ONLY']);
        $product->countries()->attach($this->togo->id);
        $this->withHeader('X-Country-Id', $this->benin->id)->getJson('/api/v1/products')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/products')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_duo_dashboard_country_selection_limits_people_and_kpis_together(): void
    {
        foreach ([$this->delegate, $this->other] as $user) {
            CoachingSession::create(['session_type' => 'DUO', 'status' => 'completed',
                'supervisor_id' => $this->admin->id, 'delegate_id' => $user->id, 'evaluated_id' => $user->id,
                'date_duo' => now()->toDateString(), 'note_sur_20' => 16]);
        }
        $this->withHeader('X-Country-Id', $this->togo->id)->getJson('/api/v1/coaching/duo-dashboard')
            ->assertOk()->assertJsonCount(1, 'delegates')->assertJsonPath('kpis.total_duos', 1)
            ->assertJsonPath('delegates.0.delegate_id', $this->delegate->id);
    }

    public function test_supervisor_dashboard_respects_the_selected_date_range(): void
    {
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $supervisor = User::factory()->create();
        $supervisor->assignRole('superviseur');
        $this->delegate->update(['manager_id' => $supervisor->id]);
        foreach (['2026-09-01', '2026-10-01'] as $date) {
            Activity::create(['activity_type' => 'visite', 'user_id' => $this->delegate->id,
                'date_activite' => $date, 'statut' => 'realisee']);
        }
        Sanctum::actingAs($supervisor);
        $this->getJson('/api/v1/supervisor/dashboard?start_date=2026-09-01&end_date=2026-09-01')
            ->assertOk()->assertJsonPath('kpis.0.value', '1');
        $this->getJson('/api/v1/supervisor/dashboard?start_date=2026-09-02&end_date=2026-09-03')
            ->assertOk()->assertJsonPath('kpis.0.value', '0');
        $this->getJson('/api/v1/supervisor/dashboard?start_date=2026-09-03&end_date=2026-09-01')
            ->assertUnprocessable();
    }
}
