# Document de Spécifications REST API & Requirements Backend (CRM-SKYNITY)
## Analyse Complète Écran par Écran (Page-by-Page) & Seeders Laravel Requis

Ce document répertorie la totalité des routes API, des méthodes HTTP, des paramètres de requête, des structures de payloads, des codes d'erreur, des règles métier et **de tous les Seeders Laravel nécessaires pour le peuplement de la base de données**.

---

## Sommaire des Écrans & Seeders Analysés

1. [MODULE AUTHENTIFICATION & SÉCURITÉ](#1-module-authentification--sécurité)
2. [MODULE DÉLÉGUÉ - ACCUEIL & GÉOLOCALISATION](#2-module-délégué---accueil--géolocalisation)
3. [MODULE VISITES & EXÉCUTION TERRAIN](#3-module-visites--exécution-terrain)
4. [MODULE RÉFÉRENTIEL HCP & KOL](#4-module-référentiel-hcp--kol)
5. [MODULE SUPPORTS ADV, GED & PRODUITS](#5-module-supports-adv-ged--produits)
6. [MODULE ÉVÉNEMENTS, CAMPAGNES & PHARMACOVIGILANCE](#6-module-événements-campagnes--pharmacovigilance)
7. [MODULE MESSAGERIE, NOTIFICATIONS, FRAIS & PROFIL](#7-module-messagerie-notifications-frais--profil)
8. [MODULE MANAGER & SUPERVISEUR](#8-module-manager--superviseur)
9. [TABLEAU RÉCAPITULATIF GLOBAL DES ROUTES API](#tableau-récapitulatif-global-de-toutes-les-routes-api)
10. [SEEDERS LARAVEL REQUIS POUR L'EXÉCUTION COMPLÈTE](#10-seeders-laravel-requis-pour-lexécution-complète)
    - 10.1 `UserSeeder.php` (Utilisateurs, Roles & Zones)
    - 10.2 `AccountSeeder.php` (Hôpitaux, CHU & Cliniques)
    - 10.3 `ContactSeeder.php` / `HcpSeeder.php` (Professionnels de Santé Réels)
    - 10.4 `ProductSeeder.php` (Gamme Médicamenteuse & Posologies)
    - 10.5 `ActivitySeeder.php` (Tournées & Visites Réelles)
    - 10.6 `DocumentSeeder.php` (Base Documentaire ANSM & GED)
    - 10.7 `StockSeeder.php` (Dotations d'Échantillons Médicaux)
    - 10.8 `EventSeeder.php` (Événements & Congrès)
    - 10.9 `ExpenseSeeder.php` (Notes de Frais)
    - 10.10 `CampaignSeeder.php` (Campagnes Marketing & Médicales)
    - 10.11 `NotificationSeeder.php` (Notifications Système & Rappels)
    - 10.12 `ConversationMessageSeeder.php` (Messagerie Interne Délégué/Manager)
    - 10.13 `OrderSeeder.php` (Commandes & Réapprovisionnements)
    - 10.14 `PharmacovigilanceSeeder.php` (Signalements d'Effets Indésirables)
    - 10.15 `DigitalSupportSeeder.php` (ADV / E-Detailing Interactif)
    - 10.16 `DatabaseSeeder.php` (Ordre d'Exécution Global & Importation)
    - 10.17 `Commandes Artisan pour Réinitialisation & Seeding`

---

## 1. MODULE AUTHENTIFICATION & SÉCURITÉ

### 1.1 Page `LoginView` (`lib/presentation/views/auth/login_view.dart`)
- **Rôle** : Connexion initiale de l'utilisateur (Délégué, Superviseur ou Manager).
- **Route** : `POST /api/v1/login`
- **Payload Request** :
  ```json
  {
    "email": "delegue@crm.local",
    "password": "password",
    "device_name": "iPhone / Android Simulator / MacOS"
  }
  ```
- **Réponse Succès (200 OK)** :
  ```json
  {
    "token": "1|eyJhbGciOiJKV1QiLCJ...",
    "user": {
      "id": "usr_99812",
      "name": "Alice Déléguée",
      "email": "delegue@crm.local",
      "role": "delegue",
      "subsidiary_id": "sub_ci_01",
      "zone": "Abidjan Est"
    }
  }
  ```
- **Erreurs HTTP** : `401 Unauthorized` (Identifiants invalides), `422 Unprocessable Entity` (Champs requis manquants).

### 1.2 Page `ForgotPasswordView` (`lib/presentation/views/auth/forgot_password_view.dart`)
- **Route** : `POST /api/v1/password/email`
- **Payload** : `{ "email": "delegue@crm.local" }`

### 1.3 Page `SecurityVerificationView` (`lib/presentation/views/auth/security_verification_view.dart`)
- **Route** : `POST /api/v1/security/verify-otp`

---

## 2. MODULE DÉLÉGUÉ - ACCUEIL & GÉOLOCALISATION

### 2.1 Page `DelegateDashboardView` (`lib/presentation/views/delegate/delegate_dashboard_view.dart`)
- **Route** : `GET /api/v1/analytics/dashboard`
- **Route** : `GET /api/v1/activities` (Bandeau et liste des activités du jour avec vraies heures et vrais médecins).

### 2.2 Page `DelegateTourneeView` (`lib/presentation/views/delegate/delegate_tournee_view.dart`)
- **Route** : `GET /api/v1/tournees/today?lat={lat}&lng={lng}`
- **Calculs Backend Requis** : Distance cumulative (km) depuis la position GPS du délégué vers l'arrêt 1, puis entre arrêts $1 \to 2 \to 3$. Durée estimée totale (trajets + 30 min par visite).
- **Route** : `POST /api/v1/tournees/optimize` (Optimisation IA des points de passage).

### 2.3 Page `DelegateVisitsView` (`lib/presentation/views/delegate/delegate_visits_view.dart`)
- **Route** : `GET /api/v1/visites` (Filtrage par onglets: Aujourd'hui, Planifiées, Réalisées, Manquées).

---

## 3. MODULE VISITES & EXÉCUTION TERRAIN

### 3.1 Page `WeeklyPlanningView` (`lib/presentation/views/visits/weekly_planning_view.dart`)
- **Route** : `GET /api/v1/plannings/current`
- **Route** : `POST /api/v1/visites` (Création visite)
- **VALEUR CRITIQUE / REGLE METIER** : **Le backend DOIT REJETER toute tentative d'ajout ou de programmation d'une visite dont la date et l'heure (`planned_at`) sont situées dans le passé (`planned_at < NOW()`).**
- **Code HTTP 422 Unprocessable Entity** :
  ```json
  {
    "message": "La date et l'heure de la visite ne peuvent pas être situées dans le passé.",
    "errors": {
      "planned_at": ["Impossible de programmer une visite à une date ou heure passée."]
    }
  }
  ```
- **Route** : `PUT /api/v1/visites/{id}` (Édition intégrale - même contrôle des dates/heures passées).
- **Route** : `POST /api/v1/plannings/{id}/submit` (Soumission au superviseur pour la deadline du vendredi).

### 3.2 Page `VisitExecutionView` (`lib/presentation/views/visits/visit_execution_view.dart`)
- **Route** : `POST /api/v1/visites/{id}/start` (Check-in GPS `gps_debut_lat`, `gps_debut_lng`).
- **Route** : `POST /api/v1/visites/{id}/complete` (Check-out GPS, produits présentés, prises de commandes, audit stock client).

---

## 4. MODULE RÉFÉRENTIEL HCP & KOL

- `GET /api/v1/hcps` & `GET /api/v1/hcps/{id}` (Référentiel des médecins et fiche 360º)
- `GET /api/v1/kols` & `POST /api/v1/kols` (Gestion des Key Opinion Leaders)

---

## 5. MODULE SUPPORTS ADV, GED & PRODUITS

- `GET /api/v1/documents` (Base documentaire ANSM : brochures, RCP, vidéos)
- `GET /api/v1/products` (Catalogue médicaments et posologies)
- `GET /api/v1/digital-supports` (Diaporamas d'ADV E-Detailing)

---

## 6. MODULE ÉVÉNEMENTS, CAMPAGNES & PHARMACOVIGILANCE

- `GET /api/v1/events` & `POST /api/v1/events/{id}/checkin`
- `GET /api/v1/campaigns`
- `POST /api/v1/pharmacovigilance/reports`

---

## 7. MODULE MESSAGERIE, NOTIFICATIONS, FRAIS & PROFIL

- `GET /api/v1/conversations` & `POST /api/v1/conversations/{id}/messages`
- `GET /api/v1/notifications` & `POST /api/v1/notifications/mark-read`
- `GET /api/v1/expenses` & `POST /api/v1/expenses`

---

## 8. MODULE MANAGER & SUPERVISEUR

- `GET /api/v1/analytics/manager` (KPIs d'équipe)
- `POST /api/v1/plannings/{id}/validate` (Validation de planning)
- `POST /api/v1/plannings/duo` (Visite accompagnant DUO)
- `GET /api/v1/supervisor/team-locations` (Géolocalisation en temps réel)

---

## Tableau Récapitulatif Global de Toutes les Routes API

| Module / Écran | Méthode | Route REST API | Description / Règles RIGOUREUSES |
| :--- | :--- | :--- | :--- |
| **Auth / Login** | `POST` | `/api/v1/login` | Connexion et génération du token Sanctum |
| **Auth / User** | `GET` | `/api/v1/me` | Récupération des infos utilisateur et zone |
| **Dashboard** | `GET` | `/api/v1/analytics/dashboard` | KPIs d'accueil (visites, médecins, activités) |
| **Tournée** | `GET` | `/api/v1/tournees/today?lat={lat}&lng={lng}` | Distance totale GPS & durée estimée dynamique |
| **Tournée** | `POST` | `/api/v1/tournees/optimize` | Optimisation IA des points de passage |
| **Visites** | `GET` | `/api/v1/visites` | Récupération des visites par statut/onglet |
| **Visites** | `POST` | `/api/v1/visites` | Création visite (**REJET STRICT SI DATE/HEURE PASSÉE - 422**) |
| **Visites** | `PUT` | `/api/v1/visites/{id}` | Édition intégrale (**REJET STRICT SI DATE/HEURE PASSÉE - 422**) |
| **Visites** | `DELETE`| `/api/v1/visites/{id}` | Suppression d'une visite |
| **Visites / Check-in**| `POST` | `/api/v1/visites/{id}/start` | Démarrage visite avec lat/lng GPS |
| **Visites / Check-out**| `POST` | `/api/v1/visites/{id}/complete` | Clôture visite (lat/lng, commande, stock) |
| **Planning** | `GET` | `/api/v1/plannings/current` | Récupération du planning de la semaine |
| **Planning** | `POST` | `/api/v1/plannings/{id}/submit` | Soumission du planning hebdo au superviseur |
| **Planning** | `POST` | `/api/v1/plannings/{id}/validate` | Validation du planning par le superviseur |
| **Planning** | `POST` | `/api/v1/plannings/duo` | Programmation d'une visite en Duo |
| **HCP** | `GET` | `/api/v1/hcps` | Référentiel complet des professionnels de santé |
| **HCP** | `GET` | `/api/v1/hcps/{id}` | Fiche détaillée 360º d'un médecin |
| **KOL** | `GET` | `/api/v1/kols` | Liste des Key Opinion Leaders |
| **Établissements**| `GET` | `/api/v1/accounts` | Référentiel des hôpitaux, CHU, cliniques |
| **GED / Docs** | `GET` | `/api/v1/documents` | Base documentaire ANSM (brochures, RCP, vidéos) |
| **Produits** | `GET` | `/api/v1/products` | Catalogue produits et posologies |
| **ADV** | `GET` | `/api/v1/digital-supports` | Supports de présentation E-Detailing |
| **Stocks** | `GET` | `/api/v1/stocks/my-samples` | Solde des échantillons en possession du délégué |
| **Événements** | `GET` | `/api/v1/events` | Réunions staff, congrès et événements |
| **Pharmacovigilance**| `POST` | `/api/v1/pharmacovigilance/reports` | Déclaration réglementaire d'effet indésirable |
| **Frais** | `GET` | `/api/v1/expenses` | Liste des notes de frais |
| **Frais** | `POST` | `/api/v1/expenses` | Soumission note de frais avec reçu |
| **Manager** | `GET` | `/api/v1/analytics/manager` | Tableau de bord synthétique du manager |
| **Manager** | `GET` | `/api/v1/supervisor/team-locations` | Géolocalisation en temps réel de l'équipe |

---

## 10. SEEDERS LARAVEL REQUIS POUR L'EXÉCUTION COMPLÈTE

Cette section fournit le code source PHP complet de tous les **Database Seeders Laravel** nécessaires pour alimenter la base de données avec des données réelles et professionnelles sans aucun placeholder générique.

### 10.1 `UserSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Délégué Médical principal
        User::updateOrCreate(
            ['email' => 'delegue@crm.local'],
            [
                'id' => Str::uuid(),
                'name' => 'Alice Déléguée',
                'password' => Hash::make('password'),
                'role' => 'delegue',
                'phone' => '+225 0707070707',
                'zone' => 'Abidjan Est',
                'is_active' => true,
            ]
        );

        // 2. Superviseur / Manager Régional
        User::updateOrCreate(
            ['email' => 'superviseur@crm.local'],
            [
                'id' => Str::uuid(),
                'name' => 'Marc Diallo (Manager)',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'phone' => '+225 0808080808',
                'zone' => 'Côte d\'Ivoire Global',
                'is_active' => true,
            ]
        );
    }
}
```

### 10.2 `AccountSeeder.php` (Hôpitaux, CHU & Cliniques)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'id' => Str::uuid(),
                'nom' => 'Clinique Saint-Jean',
                'type' => 'Clinique Privée',
                'adresse' => 'Boulevard Latrille, Cocody, Abidjan',
                'ville' => 'Abidjan',
                'secteur' => 'Abidjan Est',
                'telephone' => '+225 2722445566',
                'lat' => 5.3599,
                'lng' => -4.0083,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'Polyclinique Sainte-Anne',
                'type' => 'Polyclinique',
                'adresse' => 'Rue des Jardins, Deux-Plateaux',
                'ville' => 'Abidjan',
                'secteur' => 'Abidjan Nord',
                'telephone' => '+225 2722411122',
                'lat' => 5.3712,
                'lng' => -4.0011,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'CHU de Cocody',
                'type' => 'Hôpital Public CHU',
                'adresse' => 'Université Félix Houphouët-Boigny, Cocody',
                'ville' => 'Abidjan',
                'secteur' => 'Abidjan Est',
                'telephone' => '+225 2722449000',
                'lat' => 5.3480,
                'lng' => -3.9870,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'Hôpital Principal de Dakar',
                'type' => 'Hôpital Militaire / Public',
                'adresse' => 'Avenue Nelson Mandela, Dakar',
                'ville' => 'Dakar',
                'secteur' => 'Dakar Plateau',
                'telephone' => '+221 338395050',
                'lat' => 14.6622,
                'lng' => -17.4361,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('accounts')->insert($accounts);
    }
}
```

### 10.3 `ContactSeeder.php` / `HcpSeeder.php` (Médecins Réels)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $hcps = [
            [
                'id' => Str::uuid(),
                'prenom' => 'Jean',
                'nom' => 'Aka',
                'nom_complet' => 'Dr. Jean Aka',
                'specialite' => 'Cardiologue',
                'statut' => 'actif',
                'potentiel_prescription' => 'A (Très Élevé)',
                'telephone' => '+225 0701020304',
                'email' => 'dr.aka@saintjean.ci',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'prenom' => 'Marie',
                'nom' => 'Koné',
                'nom_complet' => 'Dr. Marie Koné',
                'specialite' => 'Pédiatre Néonatologiste',
                'statut' => 'prioritaire',
                'potentiel_prescription' => 'A (Très Élevé)',
                'telephone' => '+225 0705060708',
                'email' => 'dr.kone@sainteanne.ci',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'prenom' => 'Ibrahim',
                'nom' => 'Sow',
                'nom_complet' => 'Dr. Ibrahim Sow',
                'specialite' => 'Médecin Généraliste',
                'statut' => 'en_suivi',
                'potentiel_prescription' => 'B (Moyen)',
                'telephone' => '+221 776123456',
                'email' => 'dr.sow@dakar.sn',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'prenom' => 'Aminata',
                'nom' => 'Diallo',
                'nom_complet' => 'Dr. Aminata Diallo',
                'specialite' => 'Gynécologue-Obstétricienne',
                'statut' => 'actif',
                'potentiel_prescription' => 'A (Très Élevé)',
                'telephone' => '+225 0709101112',
                'email' => 'dr.diallo@chu.ci',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'prenom' => 'Charles',
                'nom' => 'N\'Dri',
                'nom_complet' => 'Dr. Charles N\'Dri',
                'specialite' => 'Neurologue',
                'statut' => 'prioritaire',
                'potentiel_prescription' => 'A (Très Élevé)',
                'telephone' => '+225 0713141516',
                'email' => 'dr.ndri@pasteur.ci',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('contacts')->insert($hcps);
    }
}
```

### 10.4 `ProductSeeder.php` (Gamme Médicamenteuse)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => Str::uuid(),
                'nom' => 'CardioPulse 50mg',
                'classe_therapeutique' => 'Anti hypertenseur / Cardiologie',
                'forme' => 'Comprimé sécable',
                'posologie' => '1 comprimé par jour au petit-déjeuner',
                'prix_public' => 4500.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'VasoProtect 10mg',
                'classe_therapeutique' => 'Protecteur Vasculaire / Veinotonique',
                'forme' => 'Gélule',
                'posologie' => '2 gélules par jour lors des repas',
                'prix_public' => 3800.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'LipidControl 20mg',
                'classe_therapeutique' => 'Hypolipémiant / Statine',
                'forme' => 'Comprimé pelliculé',
                'posologie' => '1 comprimé le soir au coucher',
                'prix_public' => 6200.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'nom' => 'NeoPulse 100mg',
                'classe_therapeutique' => 'Cardioprotecteur / Antiagrégant',
                'forme' => 'Comprimé gastro-résistant',
                'posologie' => '1 comprimé par jour',
                'prix_public' => 2900.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('products')->insert($products);
    }
}
```

### 10.5 `ActivitySeeder.php` (Tournées & Visites Réelles)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id') ?? Str::uuid();
        $contactAka = DB::table('contacts')->where('nom_complet', 'LIKE', '%Aka%')->value('id');
        $contactKone = DB::table('contacts')->where('nom_complet', 'LIKE', '%Koné%')->value('id');
        $contactSow = DB::table('contacts')->where('nom_complet', 'LIKE', '%Sow%')->value('id');
        $contactDiallo = DB::table('contacts')->where('nom_complet', 'LIKE', '%Diallo%')->value('id');

        $activities = [
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'contact_id' => $contactAka,
                'contact_name' => 'Dr. Jean Aka',
                'hcp_specialty' => 'Cardiologue',
                'account_name' => 'Clinique Saint-Jean',
                'zone' => 'Abidjan Est',
                'activity_type' => 'visite',
                'date_activite' => now()->format('Y-m-d'),
                'heure_debut' => '09:00',
                'heure_fin' => '09:45',
                'duree_minutes' => 45,
                'gps_debut_lat' => 5.3599,
                'gps_debut_lng' => -4.0083,
                'statut' => 'realisee',
                'planning_status' => 'validated',
                'notes' => 'Présentation étude Phase III CardioPulse. Intérêt marqué du médecin.',
                'is_locked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'contact_id' => $contactKone,
                'contact_name' => 'Dr. Marie Koné',
                'hcp_specialty' => 'Pédiatre',
                'account_name' => 'Polyclinique Sainte-Anne',
                'zone' => 'Abidjan Nord',
                'activity_type' => 'visite_duo',
                'date_activite' => now()->format('Y-m-d'),
                'heure_debut' => '10:30',
                'heure_fin' => '11:15',
                'duree_minutes' => 45,
                'gps_debut_lat' => 5.3712,
                'gps_debut_lng' => -4.0011,
                'statut' => 'en_cours',
                'planning_status' => 'validated',
                'is_duo' => true,
                'duo_manager_name' => 'Marc Diallo (Manager)',
                'notes' => 'Visite accompagnement DUO pour la gamme pédiatrique.',
                'is_locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'contact_id' => $contactSow,
                'contact_name' => 'Dr. Ibrahim Sow',
                'hcp_specialty' => 'Médecin Généraliste',
                'account_name' => 'Hôpital Principal',
                'zone' => 'Abidjan Est',
                'activity_type' => 'visite',
                'date_activite' => now()->format('Y-m-d'),
                'heure_debut' => '14:00',
                'heure_fin' => '14:30',
                'duree_minutes' => 30,
                'gps_debut_lat' => 5.3480,
                'gps_debut_lng' => -3.9870,
                'statut' => 'planifiee',
                'planning_status' => 'validated',
                'notes' => 'Remise de la brochure posologie VasoProtect.',
                'is_locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'contact_id' => $contactDiallo,
                'contact_name' => 'Dr. Aminata Diallo',
                'hcp_specialty' => 'Gynécologue',
                'account_name' => 'CHU de Cocody',
                'zone' => 'Abidjan Est',
                'activity_type' => 'visite',
                'date_activite' => now()->format('Y-m-d'),
                'heure_debut' => '15:30',
                'heure_fin' => '16:15',
                'duree_minutes' => 45,
                'gps_debut_lat' => 5.3450,
                'gps_debut_lng' => -3.9820,
                'statut' => 'planifiee',
                'planning_status' => 'validated',
                'notes' => 'Suivi des prescriptions et présentation LipidControl.',
                'is_locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('activities')->insert($activities);
    }
}
```

### 10.6 `DocumentSeeder.php` (Base Documentaire ANSM)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'id' => Str::uuid(),
                'reference' => 'DOC-CARDIO-01',
                'title' => 'Étude Clinique Phase III CardioPulse',
                'description' => 'Réduction de la mortalité cardiovasculaire chez les hypertendus sévères',
                'produit' => 'CardioPulse',
                'type' => 'pdf',
                'file_path' => '/storage/docs/etude_cardiopulse_phase3.pdf',
                'version' => '2.4',
                'file_size' => 14200000,
                'is_active' => true,
                'expiration_date' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'reference' => 'DOC-VASO-02',
                'title' => 'Brochure Posologie & Recommandations VasoProtect',
                'description' => 'Guide de prescription cabinet et interactions médicamenteuses',
                'produit' => 'VasoProtect',
                'type' => 'pdf',
                'file_path' => '/storage/docs/brochure_vasoprotect_posologie.pdf',
                'version' => '1.8',
                'file_size' => 4800000,
                'is_active' => true,
                'expiration_date' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'reference' => 'DOC-LIPID-03',
                'title' => 'E-Detailing Présentation Staff LipidControl',
                'description' => 'Support interactif de présentation pour staffs hospitaliers et congrès',
                'produit' => 'LipidControl',
                'type' => 'presentation',
                'file_path' => '/storage/docs/edetailing_lipidcontrol.pptx',
                'version' => '3.0',
                'file_size' => 28500000,
                'is_active' => true,
                'expiration_date' => now()->addMonths(8),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'reference' => 'DOC-VIDEO-04',
                'title' => 'Vidéo Démonstration Injection CardioPulse',
                'description' => 'Tutoriel vidéo haute définition d\'administration au patient',
                'produit' => 'CardioPulse',
                'type' => 'video',
                'file_path' => '/storage/docs/tuto_injection_cardiopulse.mp4',
                'version' => '1.0',
                'file_size' => 45000000,
                'is_active' => true,
                'expiration_date' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'reference' => 'DOC-RCP-05',
                'title' => 'Fiche Résumé des Caractéristiques du Produit (RCP) VasoProtect',
                'description' => 'Mentions légales, composition et pharmacovigilance obligatoire',
                'produit' => 'VasoProtect',
                'type' => 'pdf',
                'file_path' => '/storage/docs/rcp_vasoprotect.pdf',
                'version' => '4.1',
                'file_size' => 1200000,
                'is_active' => true,
                'expiration_date' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('documents')->insert($documents);
    }
}
```

### 10.7 `StockSeeder.php` (Dotation d'Échantillons Médicaux)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');
        $products = DB::table('products')->get();

        foreach ($products as $prod) {
            DB::table('sample_stocks')->insert([
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'product_id' => $prod->id,
                'product_name' => $prod->nom,
                'quantity_allocated' => 50,
                'quantity_remaining' => 38,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

### 10.8 `EventSeeder.php` & `CampaignSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('events')->insert([
            [
                'id' => Str::uuid(),
                'title' => 'Congrès Européen d\'Athérosclérose 2026',
                'location' => 'Palais des Congrès, Abidjan',
                'event_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'description' => 'Présentation des nouvelles recommandations internationales en Cardiologie.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => ' Table Ronde Pédiatrique Q3',
                'location' => 'Polyclinique Sainte-Anne',
                'event_date' => now()->addDays(12)->format('Y-m-d H:i:s'),
                'description' => 'Échange d\'expérience entre pédiatres néonatologistes.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.9 `ExpenseSeeder.php` (Notes de Frais)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');

        DB::table('expenses')->insert([
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'category' => 'Carburant / Transport',
                'amount' => 25000.0,
                'currency' => 'XOF',
                'date_expense' => now()->subDays(2)->format('Y-m-d'),
                'description' => 'Frais de carburant tournée Abidjan Est',
                'status' => 'en_attente',
                'receipt_path' => '/storage/receipts/recu_carburant_01.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'category' => 'Restauration / Déjeuner Médecin',
                'amount' => 45000.0,
                'currency' => 'XOF',
                'date_expense' => now()->subDays(5)->format('Y-m-d'),
                'description' => 'Déjeuner de travail avec Dr. Jean Aka',
                'status' => 'valide',
                'receipt_path' => '/storage/receipts/recu_resto_02.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.10 `CampaignSeeder.php` (Campagnes Marketing & Médicales)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('campaigns')->insert([
            [
                'id' => Str::uuid(),
                'title' => 'Campagne Lancement CardioPulse Q3',
                'objective' => 'Sensibilisation de 150 cardiologues au nouveau protocole d\'hypertension',
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
                'end_date' => now()->addMonths(2)->endOfMonth()->format('Y-m-d'),
                'target_specialties' => json_encode(['Cardiologue', 'Médecin Généraliste']),
                'budget' => 5000000.0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Campagne Pédiatrie VasoProtect Junior',
                'objective' => 'Présentation de la formule sirop dans les CHU et cliniques pédiatriques',
                'start_date' => now()->subMonth()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'target_specialties' => json_encode(['Pédiatre', 'Néonatologiste']),
                'budget' => 3500000.0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.11 `NotificationSeeder.php` (Notifications Système & Rappels)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');

        DB::table('notifications')->insert([
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'title' => 'Planning Hebdomadaire Validé',
                'message' => 'Votre superviseur Marc Diallo a validé votre planning pour la semaine en cours.',
                'type' => 'planning_validation',
                'is_read' => true,
                'read_at' => now()->subHours(5),
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(5),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'title' => 'Nouveau Document ANSM Publié',
                'message' => 'L\'étude clinique Phase III CardioPulse v2.4 est disponible dans la GED.',
                'type' => 'document_upload',
                'is_read' => false,
                'read_at' => null,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'title' => 'Rappel Visite Médicale',
                'message' => 'Visite programmée avec Dr. Jean Aka à 09:00 (Clinique Saint-Jean).',
                'type' => 'visit_reminder',
                'is_read' => false,
                'read_at' => null,
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ]
        ]);
    }
}
```

### 10.12 `ConversationMessageSeeder.php` (Messagerie Interne Délégué/Manager)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationMessageSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');
        $managerId = DB::table('users')->where('email', 'superviseur@crm.local')->value('id');

        $conversationId = Str::uuid();

        DB::table('conversations')->insert([
            'id' => $conversationId,
            'title' => 'Équipe Abidjan Est - Coaching & Visites DUO',
            'type' => 'direct',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversation_user')->insert([
            ['conversation_id' => $conversationId, 'user_id' => $delegueId, 'created_at' => now(), 'updated_at' => now()],
            ['conversation_id' => $conversationId, 'user_id' => $managerId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('messages')->insert([
            [
                'id' => Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $managerId,
                'content' => 'Bonjour Alice, n\'oublie pas de valider la visite en DUO avec le Dr. Marie Koné à 10:30.',
                'is_read' => true,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            [
                'id' => Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $delegueId,
                'content' => 'Bien reçu Marc ! Les échantillons et le support ADV sont prêts.',
                'is_read' => true,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ]
        ]);
    }
}
```

### 10.13 `OrderSeeder.php` (Commandes & Réapprovisionnements)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');
        $accountAka = DB::table('accounts')->where('nom', 'LIKE', '%Saint-Jean%')->value('id');

        DB::table('orders')->insert([
            [
                'id' => Str::uuid(),
                'reference' => 'ORD-2026-0089',
                'user_id' => $delegueId,
                'account_id' => $accountAka,
                'total_amount' => 180000.0,
                'status' => 'livree',
                'order_date' => now()->subDays(3)->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.14 `PharmacovigilanceSeeder.php` (Signalements d'Effets Indésirables)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PharmacovigilanceSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id');
        $contactAka = DB::table('contacts')->where('nom_complet', 'LIKE', '%Aka%')->value('id');
        $prodCardio = DB::table('products')->where('nom', 'LIKE', '%CardioPulse%')->value('id');

        DB::table('pharmacovigilance_reports')->insert([
            [
                'id' => Str::uuid(),
                'user_id' => $delegueId,
                'contact_id' => $contactAka,
                'product_id' => $prodCardio,
                'patient_initials' => 'J.D.',
                'patient_age' => 58,
                'patient_gender' => 'M',
                'adverse_event_description' => 'Céphalées modérées signalées 2 heures après la première prise de CardioPulse 50mg.',
                'severity' => 'moderee',
                'report_date' => now()->subDays(1)->format('Y-m-d'),
                'status' => 'soumis',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.15 `DigitalSupportSeeder.php` (ADV / E-Detailing Interactif)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigitalSupportSeeder extends Seeder
{
    public function run(): void
    {
        $prodCardio = DB::table('products')->where('nom', 'LIKE', '%CardioPulse%')->value('id');

        DB::table('digital_supports')->insert([
            [
                'id' => Str::uuid(),
                'title' => 'ADV CardioPulse 2026 - E-Detailing Interactif',
                'product_id' => $prodCardio,
                'version' => '3.2',
                'total_slides' => 12,
                'file_path' => '/storage/adv/cardiopulse_interactive_v3.zip',
                'thumbnail_url' => '/storage/adv/thumbnails/cardiopulse_thumb.png',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
```

### 10.16 `DatabaseSeeder.php` (Ordre d'Exécution Global)
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with complete, clean operational data.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AccountSeeder::class,
            ContactSeeder::class,
            ProductSeeder::class,
            ActivitySeeder::class,
            DocumentSeeder::class,
            StockSeeder::class,
            EventSeeder::class,
            ExpenseSeeder::class,
            CampaignSeeder::class,
            NotificationSeeder::class,
            ConversationMessageSeeder::class,
            OrderSeeder::class,
            PharmacovigilanceSeeder::class,
            DigitalSupportSeeder::class,
        ]);
    }
}
```

### 10.17 Commandes Artisan pour Réinitialisation & Seeding

Pour réinitialiser proprement la base de données et charger la totalité des données d'exécution mobile :

```bash
# 1. Réinitialiser la base de données et exécuter TOUS les seeders
php artisan migrate:fresh --seed

# 2. Exécuter un seeder individuel si nécessaire
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=ActivitySeeder
php artisan db:seed --class=DocumentSeeder
php artisan db:seed --class=CampaignSeeder
php artisan db:seed --class=NotificationSeeder
```

