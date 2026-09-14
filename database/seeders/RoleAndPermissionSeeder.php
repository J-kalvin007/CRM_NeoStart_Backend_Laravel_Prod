<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;


class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyage du cache Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Définition de toutes les permissions par module ─────────────────
        $permissionsByModule = [

            'Utilisateurs' => [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'users.manage-roles',
            ],

            'Rôles & Permissions' => [
                'roles.view',
                'roles.create',
                'roles.edit',
                'roles.delete',
            ],


            'Comptes & Contacts' => [
                'accounts.view',
                'accounts.create',
                'accounts.edit',
                'accounts.delete',
                'contacts.view',
                'contacts.create',
                'contacts.edit',
                'contacts.delete',
            ],

            'Produits & Catalogue' => [
                'products.view',
                'products.create',
                'products.edit',
                'products.delete',
                'categories.view',
                'categories.create',
                'categories.edit',
                'categories.delete',
            ],

            'Activités Terrain' => [
                'activities.view',
                'activities.view-all',
                'activities.create',
                'activities.edit',
                'activities.delete',
                'tour-plans.view',
                'tour-plans.view-all',
                'tour-plans.create',
                'tour-plans.edit',
                'tour-plans.delete',
            ],

            'Tâches' => [
                'tasks.view',
                'tasks.view-all',
                'tasks.create',
                'tasks.edit',
                'tasks.delete',
            ],

            'Cycle Commercial' => [
                'opportunities.view',
                'opportunities.view-all',
                'opportunities.create',
                'opportunities.edit',
                'opportunities.delete',
                'orders.view',
                'orders.view-all',
                'orders.create',
                'orders.edit',
                'orders.delete',
                'sales.view',
                'sales.view-all',
                'sales.create',
                'sales.edit',
                'sales.delete',
            ],

            'Notes de Frais' => [
                'expenses.view',
                'expenses.view-all',
                'expenses.create',
                'expenses.edit',
                'expenses.delete',
                'expenses.submit',
                'expenses.approve',
                'expenses.reject',
            ],

            'Événements & Marketing' => [
                'events.view',
                'events.create',
                'events.edit',
                'events.delete',
                'campaigns.view',
                'campaigns.create',
                'campaigns.edit',
                'campaigns.delete',
            ],

            'Documents' => [
                'documents.view',
                'documents.create',
                'documents.edit',
                'documents.delete',
            ],

            'Échantillons' => [
                'samples.view',
                'samples.view-all',
                'samples.create',
                'samples.edit',
            ],

            'Équipes & Hiérarchie' => [
                'teams.view',
                'teams.create',
                'teams.edit',
                'teams.delete',
                'subsidiaries.view',
                'subsidiaries.create',
                'subsidiaries.edit',
                'subsidiaries.delete',
                'regions.view',
                'regions.manage',
                'territories.view',
                'territories.manage',
                'zones.view',
                'zones.manage',
                'sectors.view',
                'sectors.manage',
            ],

            'Objectifs & Performance' => [
                'objectifs.view',
                'objectifs.view-all',
                'objectifs.create',
                'objectifs.edit',
                'objectifs.delete',
            ],

            'Congés' => [
                'leaves.view',
                'leaves.view-all',
                'leaves.create',
                'leaves.approve',
                'leaves.reject',
            ],

            'Formations' => [
                'trainings.view',
                'trainings.create',
                'trainings.edit',
                'trainings.delete',
            ],

            'KOL (Leaders d\'opinion)' => [
                'kols.view',
                'kols.create',
                'kols.edit',
                'kols.delete',
            ],

            'Rapports & Analytics' => [
                'analytics.view-dashboard',
                'analytics.view-team',
                'analytics.view-global',
                'analytics.export',
            ],

            'Configuration' => [
                'config.view',
                'config.manage-account-types',
                'config.manage-contact-types',
                'config.manage-product-types',
                'config.manage-settings',
                'config.manage-custom-fields',
            ],

            'Messagerie' => [
                'messaging.view',
                'messaging.send',
            ],

            'Notifications' => [
                'notifications.view',
                'notifications.manage',
            ],

        ];

        // ── Création / mise à jour des permissions ──────────────────────────
        foreach ($permissionsByModule as $module => $permissionNames) {
            foreach ($permissionNames as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module' => $module]
                );
                // Mise à jour du module si la permission existe déjà sans module
                Permission::where('name', $name)
                    ->whereNull('module')
                    ->update(['module' => $module]);
            }
        }

        // ── Création des rôles de base ──────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin',    'guard_name' => 'web']);
        $adminPays  = Role::firstOrCreate(['name' => 'admin-pays',     'guard_name' => 'web']);
        $dirGen     = Role::firstOrCreate(['name' => 'dir-general',    'guard_name' => 'web']);
        $dirCom     = Role::firstOrCreate(['name' => 'dir-commercial', 'guard_name' => 'web']);
        $dirMed     = Role::firstOrCreate(['name' => 'dir-medical',    'guard_name' => 'web']);
        $manager    = Role::firstOrCreate(['name' => 'manager',        'guard_name' => 'web']);
        $delegue    = Role::firstOrCreate(['name' => 'delegue',        'guard_name' => 'web']);

        // Super Admin → toutes les permissions
        $superAdmin->syncPermissions(Permission::all());

        // Admin Pays → tout sauf config système avancée
        $adminPays->syncPermissions(
            Permission::whereNotIn('name', [
                'config.manage-settings',
            ])->get()
        );

        // Directeur Général → lecture globale + analytics + commercial
        $dirGen->syncPermissions(Permission::whereIn('module', [
            'Rapports & Analytics',
            'Cycle Commercial',
            'Équipes & Hiérarchie',
            'Objectifs & Performance',
            'Utilisateurs',
            'Comptes & Contacts',
        ])->orWhere('name', 'LIKE', '%.view%')
          ->orWhere('name', 'LIKE', '%.view-all%')
          ->get());

        // Directeur Commercial → commercial + équipes + terrain
        $dirCom->syncPermissions(Permission::whereIn('module', [
            'Cycle Commercial',
            'Activités Terrain',
            'Tâches',
            'Comptes & Contacts',
            'Objectifs & Performance',
            'Rapports & Analytics',
            'Échantillons',
        ])->get());

        // Directeur Médical → terrain médical + KOL + formations
        $dirMed->syncPermissions(Permission::whereIn('module', [
            'Activités Terrain',
            'KOL (Leaders d\'opinion)',
            'Formations',
            'Comptes & Contacts',
            'Rapports & Analytics',
            'Objectifs & Performance',
        ])->get());

        // Manager → vue d'équipe + terrain + validation
        $manager->syncPermissions(Permission::whereIn('name', [
            'accounts.view', 'accounts.create', 'accounts.edit',
            'contacts.view', 'contacts.create', 'contacts.edit',
            'activities.view', 'activities.view-all', 'activities.create', 'activities.edit',
            'tour-plans.view', 'tour-plans.view-all', 'tour-plans.create', 'tour-plans.edit',
            'tasks.view', 'tasks.view-all', 'tasks.create', 'tasks.edit',
            'opportunities.view', 'opportunities.view-all', 'opportunities.create', 'opportunities.edit',
            'orders.view', 'orders.view-all',
            'expenses.view', 'expenses.view-all', 'expenses.approve', 'expenses.reject',
            'objectifs.view', 'objectifs.view-all', 'objectifs.create', 'objectifs.edit',
            'samples.view', 'samples.view-all',
            'leaves.view', 'leaves.view-all', 'leaves.approve', 'leaves.reject',
            'analytics.view-dashboard', 'analytics.view-team',
            'trainings.view',
            'messaging.view', 'messaging.send',
            'notifications.view',
        ])->get());

        // Délégué médical → opérations terrain de base
        $delegue->syncPermissions(Permission::whereIn('name', [
            'accounts.view', 'accounts.create', 'accounts.edit',
            'contacts.view', 'contacts.create', 'contacts.edit',
            'activities.view', 'activities.create', 'activities.edit',
            'tour-plans.view', 'tour-plans.create', 'tour-plans.edit',
            'tasks.view', 'tasks.create', 'tasks.edit',
            'opportunities.view', 'opportunities.create', 'opportunities.edit',
            'orders.view', 'orders.create', 'orders.edit',
            'expenses.view', 'expenses.create', 'expenses.submit',
            'samples.view', 'samples.create',
            'leaves.view', 'leaves.create',
            'trainings.view',
            'documents.view',
            'messaging.view', 'messaging.send',
            'notifications.view',
            'analytics.view-dashboard',
        ])->get());

        $this->command->info('✅ ' . Permission::count() . ' permissions créées et assignées aux rôles avec succès.');
    }
}
