<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Contact;
use App\Models\Account;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Sanctum\Sanctum;

class Phase1ProfilesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Forget Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed basic roles
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'delegue_medical', 'guard_name' => 'web']);
    }

    /**
     * Test 1: Connexion avec chacun des quatre profils.
     */
    public function test_login_with_all_four_profiles(): void
    {
        $profiles = [
            'superadmin' => 'superadmin@crm.local',
            'sales_manager' => 'salesmanager@crm.local',
            'superviseur' => 'supervisor@crm.local',
            'delegue_medical' => 'delegate@crm.local',
        ];

        foreach ($profiles as $role => $email) {
            $user = User::factory()->create([
                'email' => $email,
                'password' => bcrypt('password123'),
            ]);
            $user->assignRole($role);

            $response = $this->postJson('/api/v1/login', [
                'email' => $email,
                'password' => 'password123',
            ]);

            $response->assertStatus(200);
            $this->assertTrue(
                $response->json('token') !== null || $response->json('access_token') !== null,
                "Token non trouvé dans la réponse de connexion"
            );
        }
    }

    /**
     * Test 2: Tentative de planification de visite par le Super Administrateur (Refusé - 403).
     */
    public function test_super_admin_cannot_plan_visit(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('superadmin');
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/activities', [
            'activity_type' => 'visite',
            'date_activite' => now()->addDay()->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Le Super Administrateur ne peut pas planifier de visite.']);
    }

    /**
     * Test 3: Tentative de validation d'un prescripteur par un Délégué (Refusé - 403).
     */
    public function test_delegate_cannot_validate_prescripteur(): void
    {
        $delegate = User::factory()->create();
        $delegate->assignRole('delegue_medical');
        Sanctum::actingAs($delegate);

        $contact = Contact::create([
            'nom' => 'Dr. Dupont',
            'prenom' => 'Jean',
            'user_id' => $delegate->id,
            'statut_validation' => 'en_attente',
            'statut' => 'en_attente',
        ]);

        $response = $this->postJson("/api/v1/validations/contact/{$contact->id}/approve");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Action non autorisée. Les délégués ne peuvent pas valider de fiches.']);
    }

    /**
     * Test 4: Appel direct d'un point d'entrée API réservé au Superviseur (duo creation/validation) avec un compte Délégué (Refusé - 403).
     */
    public function test_delegate_calling_supervisor_endpoint_is_forbidden(): void
    {
        $delegate = User::factory()->create();
        $delegate->assignRole('delegue_medical');
        Sanctum::actingAs($delegate);

        // Appel direct à la création de DUO (réservé au superviseur) par un délégué
        $response = $this->postJson('/api/v1/activities/duo', [
            'delegate_id' => $delegate->id,
            'date_activite' => now()->addDay()->toDateString(),
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Seul le superviseur, le Sales Manager ou la hiérarchie peut planifier des visites DUO.']);
    }

    /**
     * Test 5: Modification du rôle d'un utilisateur par le Super Administrateur (Effet immédiat).
     */
    public function test_role_change_by_super_admin_takes_immediate_effect(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('superadmin');

        $user = User::factory()->create();
        $user->assignRole('delegue_medical');

        // Initialement le délégué ne peut pas valider de fiche
        Sanctum::actingAs($user);
        $contact = Contact::create([
            'nom' => 'Dr. Martin',
            'prenom' => 'Paul',
            'user_id' => $user->id,
            'statut_validation' => 'en_attente',
            'statut' => 'en_attente',
        ]);
        $responseInitial = $this->postJson("/api/v1/validations/contact/{$contact->id}/approve");
        $responseInitial->assertStatus(403);

        // Le Super Admin change le rôle de l'utilisateur vers Superviseur
        Sanctum::actingAs($superAdmin);
        $updateResponse = $this->putJson("/api/v1/users/{$user->id}", [
            'roles' => ['superviseur'],
        ]);
        $updateResponse->assertStatus(200);

        // Immédiatement lors de sa prochaine action, l'utilisateur a le rôle Superviseur et peut valider
        Sanctum::actingAs($user->fresh());
        $responseAfterUpdate = $this->postJson("/api/v1/validations/contact/{$contact->id}/approve");
        $responseAfterUpdate->assertStatus(200);
    }
}
