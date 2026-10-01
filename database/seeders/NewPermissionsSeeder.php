<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NewPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyage du cache Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Définition de toutes les permissions par module ─────────────────
        $permissionsByModule = [

            // 1. Authentification
            'Authentification' => [
                'auth.login' => 'Connexion',
                'auth.logout' => 'Déconnexion',
                'auth.change-password' => 'Changer mot de passe',
                'auth.reset-password' => 'Réinitialiser mot de passe',
                'auth.2fa-enable' => 'Activer 2FA',
                'auth.2fa-disable' => 'Désactiver 2FA',
            ],

            // 2. Dashboard
            'Dashboard' => [
                'dashboard.view' => 'Voir dashboard',
                'dashboard.refresh' => 'Actualiser dashboard',
            ],

            // 3. Administration - Utilisateurs
            'Administration.Utilisateurs' => [
                'users.view' => 'Voir utilisateurs',
                'users.view-all' => 'Voir tous les utilisateurs',
                'users.create' => 'Créer utilisateur',
                'users.edit' => 'Modifier utilisateur',
                'users.delete' => 'Supprimer utilisateur',
                'users.manage-roles' => 'Gérer les rôles',
                'users.view-profile' => 'Voir profil utilisateur',
            ],

            // 4. Administration - Produits
            'Administration.Produits' => [
                'products.view' => 'Voir produits',
                'products.create' => 'Créer produit',
                'products.edit' => 'Modifier produit',
                'products.delete' => 'Supprimer produit',
                'categories.view' => 'Voir catégories',
                'categories.create' => 'Créer catégorie',
                'categories.edit' => 'Modifier catégorie',
                'categories.delete' => 'Supprimer catégorie',
            ],

            // 5. Administration - Hiérarchie
            'Administration.Hiérarchie' => [
                'hierarchy.view' => 'Voir hiérarchie',
                'hierarchy.create' => 'Créer unité hiérarchique',
                'hierarchy.edit' => 'Modifier hiérarchie',
                'hierarchy.delete' => 'Supprimer hiérarchie',
                'countries.view' => 'Voir pays',
                'countries.create' => 'Créer pays',
                'countries.edit' => 'Modifier pays',
                'countries.delete' => 'Supprimer pays',
                'subsidiaries.view' => 'Voir filiales',
                'subsidiaries.create' => 'Créer filiale',
                'subsidiaries.edit' => 'Modifier filiale',
                'subsidiaries.delete' => 'Supprimer filiale',
                'regions.view' => 'Voir régions',
                'regions.manage' => 'Gérer régions',
                'territories.view' => 'Voir territoires',
                'territories.manage' => 'Gérer territoires',
                'zones.view' => 'Voir zones',
                'zones.manage' => 'Gérer zones',
                'sectors.view' => 'Voir secteurs',
                'sectors.manage' => 'Gérer secteurs',
            ],

            // 6. Administration - Configuration
            'Administration.Configuration' => [
                'config.view' => 'Voir configuration',
                'config.manage-account-types' => 'Gérer types de comptes',
                'config.manage-contact-types' => 'Gérer types de contacts',
                'config.manage-product-types' => 'Gérer types de produits',
                'config.manage-settings' => 'Gérer paramètres',
                'config.manage-custom-fields' => 'Gérer champs personnalisés',
            ],

            // 7. Réseau Médical - Professionnels
            'Réseau Médical.Professionnels' => [
                'professionnels.view' => 'Voir professionnels',
                'professionnels.view-all' => 'Voir tous les professionnels',
                'professionnels.create' => 'Créer professionnel',
                'professionnels.edit' => 'Modifier professionnel',
                'professionnels.delete' => 'Supprimer professionnel',
                'professionnels.approve' => 'Approuver professionnel',
                'professionnels.reject' => 'Rejeter professionnel',
                'professionnels.import' => 'Importer professionnels',
                'specialities.view' => 'Voir spécialités',
                'specialities.create' => 'Créer spécialité',
                'specialities.edit' => 'Modifier spécialité',
                'specialities.delete' => 'Supprimer spécialité',
            ],

            // 8. Réseau Médical - Établissements
            'Réseau Médical.Établissements' => [
                'etablissements.view' => 'Voir établissements',
                'etablissements.view-all' => 'Voir tous les établissements',
                'etablissements.create' => 'Créer établissement',
                'etablissements.edit' => 'Modifier établissement',
                'etablissements.delete' => 'Supprimer établissement',
                'etablissements.approve' => 'Approuver établissement',
                'etablissements.reject' => 'Rejeter établissement',
                'etablissements.import' => 'Importer établissements',
                'account-types.view' => 'Voir types de comptes',
                'account-types.create' => 'Créer type de compte',
                'account-types.edit' => 'Modifier type de compte',
                'account-types.delete' => 'Supprimer type de compte',
            ],

            // 9. Visites & Agenda
            'Visites.Agenda' => [
                'agenda.view' => 'Voir agenda',
                'agenda.create-plan' => 'Créer plan',
                'agenda.edit-plan' => 'Modifier plan',
                'agenda.submit-week' => 'Soumettre semaine',
                'agenda.report-visit' => 'Reporter visite',
            ],
            'Visites.Visites' => [
                'visites.view' => 'Voir visites',
                'visites.view-all' => 'Voir toutes les visites',
                'visites.create' => 'Créer visite',
                'visites.edit' => 'Modifier visite',
                'visites.delete' => 'Supprimer visite',
                'visites.approve' => 'Approuver visite',
                'visites.reject' => 'Rejeter visite',
                'visites.report' => 'Reporter visite',
            ],
            'Visites.Carte' => [
                'carte.view' => 'Voir carte',
                'carte.optimize-route' => 'Optimiser itinéraire',
                'carte.gps-locate' => 'Géolocaliser',
            ],

            // 10. Échantillons
            'Échantillons' => [
                'echantillons.view' => 'Voir échantillons',
                'echantillons.view-all' => 'Voir tous les échantillons',
                'echantillons.create' => 'Créer échantillon',
                'echantillons.edit' => 'Modifier échantillon',
                'echantillons.delete' => 'Supprimer échantillon',
                'echantillons.distribute' => 'Distribuer échantillons',
                'echantillons.request-restock' => 'Demander réassort',
                'echantillons.approve-restock' => 'Approuver réassort',
                'echantillons.reject-restock' => 'Rejeter réassort',
                'echantillons.view-stock' => 'Voir stock',
            ],

            // 11. Ventes & Distribution
            'Ventes.Commandes' => [
                'commandes.view' => 'Voir commandes',
                'commandes.view-all' => 'Voir toutes les commandes',
                'commandes.create' => 'Créer commande',
                'commandes.edit' => 'Modifier commande',
                'commandes.delete' => 'Supprimer commande',
                'commandes.approve' => 'Approuver commande',
                'commandes.reject' => 'Rejeter commande',
            ],
            'Ventes.Ventes' => [
                'ventes.view' => 'Voir ventes',
                'ventes.view-all' => 'Voir toutes les ventes',
                'ventes.create' => 'Créer vente',
                'ventes.edit' => 'Modifier vente',
                'ventes.delete' => 'Supprimer vente',
            ],
            'Ventes.Distributeurs' => [
                'distributeurs.view' => 'Voir distributeurs',
                'distributeurs.create' => 'Créer distributeur',
                'distributeurs.edit' => 'Modifier distributeur',
                'distributeurs.delete' => 'Supprimer distributeur',
            ],
            'Ventes.Opportunités' => [
                'opportunites.view' => 'Voir opportunités',
                'opportunites.view-all' => 'Voir toutes les opportunités',
                'opportunites.create' => 'Créer opportunité',
                'opportunites.edit' => 'Modifier opportunité',
                'opportunites.delete' => 'Supprimer opportunité',
                'opportunites.move-stage' => 'Déplacer étape',
            ],

            // 12. Campagnes Marketing
            'Marketing.Campagnes' => [
                'campagnes.view' => 'Voir campagnes',
                'campagnes.view-all' => 'Voir toutes les campagnes',
                'campagnes.create' => 'Créer campagne',
                'campagnes.edit' => 'Modifier campagne',
                'campagnes.delete' => 'Supprimer campagne',
                'campagnes.approve' => 'Approuver campagne',
                'campagnes.change-status' => 'Changer statut',
            ],
            'Marketing.Enquêtes' => [
                'enquetes.view' => 'Voir enquêtes',
                'enquetes.create' => 'Créer enquête',
                'enquetes.edit' => 'Modifier enquête',
                'enquetes.delete' => 'Supprimer enquête',
                'enquetes.view-results' => 'Voir résultats',
            ],
            'Marketing.Événements' => [
                'evenements.view' => 'Voir événements',
                'evenements.create' => 'Créer événement',
                'evenements.edit' => 'Modifier événement',
                'evenements.delete' => 'Supprimer événement',
            ],
            'Marketing.KOL' => [
                'kol.view' => 'Voir KOL',
                'kol.create' => 'Créer KOL',
                'kol.edit' => 'Modifier KOL',
                'kol.delete' => 'Supprimer KOL',
                'kol.view-profile' => 'Voir profil KOL',
            ],

            // 13. RH & Congés
            'RH.Congés' => [
                'conges.view' => 'Voir congés',
                'conges.view-all' => 'Voir tous les congés',
                'conges.create' => 'Créer demande de congé',
                'conges.approve' => 'Approuver congé',
                'conges.reject' => 'Rejeter congé',
                'conges.view-team' => 'Voir congés équipe',
            ],

            // 14. Dépenses
            'Dépenses' => [
                'depenses.view' => 'Voir dépenses',
                'depenses.view-all' => 'Voir toutes les dépenses',
                'depenses.create' => 'Créer dépense',
                'depenses.edit' => 'Modifier dépense',
                'depenses.delete' => 'Supprimer dépense',
                'depenses.submit' => 'Soumettre dépense',
                'depenses.approve-manager' => 'Approuver (manager)',
                'depenses.approve-finance' => 'Approuver (finance)',
                'depenses.reject' => 'Rejeter dépense',
                'depenses.reimburse' => 'Rembourser',
                'depenses.ocr-scan' => 'Scan OCR',
            ],

            // 15. Analytics & Audit
            'Analytics' => [
                'analytics.view-dashboard' => 'Voir dashboard analytics',
                'analytics.view-team' => 'Voir analytics équipe',
                'analytics.view-global' => 'Voir analytics global',
                'analytics.export' => 'Exporter analytics',
                'analytics.view-edetailing' => 'Voir E-Detailing',
                'analytics.view-performance' => 'Voir performance',
            ],
            'Audit' => [
                'audit.view' => 'Voir audit',
                'audit.export' => 'Exporter audit',
                'audit.view-logs' => 'Voir logs',
            ],
            'Anomalies' => [
                'anomalies.view' => 'Voir anomalies',
                'anomalies.resolve' => 'Résoudre anomalie',
                'anomalies.request-explanation' => 'Demander explication',
            ],

            // 16. Validation Centralisée
            'Validations' => [
                'validations.view' => 'Voir validations',
                'validations.approve' => 'Approuver validation',
                'validations.reject' => 'Rejeter validation',
                'validations.view-pending' => 'Voir en attente',
            ],

            // 17. Documentation & E-Detailing
            'Documentation' => [
                'documents.view' => 'Voir documents',
                'documents.create' => 'Créer document',
                'documents.edit' => 'Modifier document',
                'documents.delete' => 'Supprimer document',
                'documents.upload' => 'Uploader document',
                'documents.view-ged' => 'Voir GED',
                'documents.view-edetailing' => 'Voir E-Detailing',
                'documents.present-fullscreen' => 'Présenter plein écran',
            ],

            // 18. Formation
            'Formation' => [
                'formations.view' => 'Voir formations',
                'formations.start' => 'Démarrer formation',
                'formations.update-progress' => 'Mettre à jour progression',
                'formations.view-certificates' => 'Voir certificats',
            ],

            // 19. Objectifs
            'Objectifs' => [
                'objectifs.view' => 'Voir objectifs',
                'objectifs.view-all' => 'Voir tous les objectifs',
                'objectifs.create' => 'Créer objectif',
                'objectifs.edit' => 'Modifier objectif',
                'objectifs.delete' => 'Supprimer objectif',
                'objectifs.update-realization' => 'Mettre à jour réalisation',
                'objectifs.view-history' => 'Voir historique',
                'objectif-types.view' => 'Voir types d\'objectifs',
                'objectif-types.create' => 'Créer type d\'objectif',
                'objectif-types.edit' => 'Modifier type d\'objectif',
                'objectif-types.delete' => 'Supprimer type d\'objectif',
            ],

            // 20. Pharmacovigilance
            'Pharmacovigilance' => [
                'pharmacovigilance.view' => 'Voir pharmacovigilance',
                'pharmacovigilance.create' => 'Créer déclaration',
                'pharmacovigilance.edit' => 'Modifier déclaration',
                'pharmacovigilance.delete' => 'Supprimer déclaration',
                'pharmacovigilance.submit' => 'Soumettre déclaration',
                'pharmacovigilance.transmit-authorities' => 'Transmettre aux autorités',
            ],

            // 21. Supervision
            'Supervision' => [
                'supervision.view' => 'Voir supervision',
                'supervision.view-team' => 'Voir équipe',
                'supervision.view-kpis' => 'Voir KPIs',
                'supervision.view-activities' => 'Voir activités',
            ],

            // 22. Profil
            'Profil' => [
                'profil.view' => 'Voir profil',
                'profil.edit' => 'Modifier profil',
                'profil.change-password' => 'Changer mot de passe',
                'profil.manage-2fa' => 'Gérer 2FA',
                'profil.manage-notifications' => 'Gérer notifications',
            ],

            // 23. Messagerie & Notifications
            'Messagerie' => [
                'messaging.view' => 'Voir messages',
                'messaging.send' => 'Envoyer message',
            ],
            'Notifications' => [
                'notifications.view' => 'Voir notifications',
                'notifications.manage' => 'Gérer notifications',
            ],

        ];

        // ── Création / mise à jour des permissions ──────────────────────────
        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'module' => $module,
                        'label' => $label,
                        'description' => "Permission $label pour le module $module"
                    ]
                );
                // Mise à jour du module et label si la permission existe déjà
                Permission::where('name', $name)
                    ->whereNull('module')
                    ->update(['module' => $module, 'label' => $label]);
            }
        }

        // ── Création des rôles de base ──────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $dirCom     = Role::firstOrCreate(['name' => 'directeur-commercial', 'guard_name' => 'web']);
        $dirMkt     = Role::firstOrCreate(['name' => 'directeur-marketing', 'guard_name' => 'web']);
        $manager    = Role::firstOrCreate(['name' => 'superviseur', 'guard_name' => 'web']);
        $delegue    = Role::firstOrCreate(['name' => 'delegue-medical', 'guard_name' => 'web']);

        // ── Assignation des permissions par rôle ───────────────────────────────

        // Super Admin → toutes les permissions
        $superAdmin->syncPermissions(Permission::all());

        // Directeur Commercial → ventes, distribution, objectifs, analytics
        $dirCom->syncPermissions(Permission::whereIn('module', [
            'Ventes.Commandes',
            'Ventes.Ventes',
            'Ventes.Distributeurs',
            'Ventes.Opportunités',
            'Objectifs',
            'Analytics',
            'Réseau Médical.Professionnels',
            'Réseau Médical.Établissements',
            'Visites.Visites',
            'Visites.Agenda',
            'Administration.Utilisateurs',
        ])->orWhere('name', 'LIKE', '%.view%')
          ->orWhere('name', 'LIKE', '%.view-all%')
          ->pluck('name')
          ->toArray());

        // Directeur Marketing → campagnes, événements, KOL, enquêtes, supports
        $dirMkt->syncPermissions(Permission::whereIn('module', [
            'Marketing.Campagnes',
            'Marketing.Enquêtes',
            'Marketing.Événements',
            'Marketing.KOL',
            'Documentation',
            'Formation',
            'Visites.Visites',
            'Réseau Médical.Professionnels',
            'Analytics',
        ])->pluck('name')
          ->toArray());

        // Superviseur → validation, supervision, équipe
        $manager->syncPermissions([
            // Visites & Agenda
            'visites.view', 'visites.view-all', 'visites.approve', 'visites.reject', 'visites.report',
            'agenda.view',
            'carte.view',
            // Réseau Médical
            'professionnels.view', 'professionnels.view-all', 'professionnels.approve', 'professionnels.reject',
            'etablissements.view', 'etablissements.view-all', 'etablissements.approve', 'etablissements.reject',
            // Échantillons
            'echantillons.view', 'echantillons.view-all', 'echantillons.approve-restock', 'echantillons.reject-restock',
            // Dépenses
            'depenses.view', 'depenses.view-all', 'depenses.approve-manager', 'depenses.reject',
            // Congés
            'conges.view', 'conges.view-all', 'conges.approve', 'conges.reject', 'conges.view-team',
            // Objectifs
            'objectifs.view', 'objectifs.view-all', 'objectifs.create', 'objectifs.edit', 'objectifs.update-realization',
            // Validation Centralisée
            'validations.view', 'validations.approve', 'validations.reject',
            // Supervision
            'supervision.view', 'supervision.view-team', 'supervision.view-kpis', 'supervision.view-activities',
            // Analytics
            'analytics.view-dashboard', 'analytics.view-team',
            // Profil
            'profil.view', 'profil.edit',
        ]);

        // Délégué Médical → terrain de base
        $delegue->syncPermissions([
            // Visites & Agenda
            'visites.view', 'visites.create', 'visites.edit', 'visites.delete',
            'agenda.view', 'agenda.create-plan', 'agenda.edit-plan', 'agenda.submit-week', 'agenda.report-visit',
            'carte.view', 'carte.gps-locate',
            // Réseau Médical
            'professionnels.view', 'professionnels.create', 'professionnels.edit',
            'etablissements.view', 'etablissements.create', 'etablissements.edit',
            // Échantillons
            'echantillons.view', 'echantillons.distribute', 'echantillons.request-restock',
            // Dépenses
            'depenses.view', 'depenses.create', 'depenses.submit', 'depenses.ocr-scan',
            // Congés
            'conges.view', 'conges.create',
            // Ventes
            'ventes.view', 'ventes.create', 'ventes.edit',
            'commandes.view', 'commandes.create',
            // Opportunités
            'opportunites.view', 'opportunites.create', 'opportunites.edit', 'opportunites.move-stage',
            // Documentation
            'documents.view', 'documents.view-edetailing', 'documents.present-fullscreen',
            // Formation
            'formations.view', 'formations.start', 'formations.update-progress',
            // Profil
            'profil.view', 'profil.edit', 'profil.change-password', 'profil.manage-2fa',
            // Notifications
            'notifications.view',
            'messaging.view', 'messaging.send',
            // Dashboard
            'dashboard.view',
            // Analytics limité
            'analytics.view-dashboard',
        ]);

        $this->command->info('✅ ' . Permission::count() . ' permissions créées et assignées aux rôles avec succès.');
    }
}
