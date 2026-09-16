# CRM-SKYNITY Backend API (Laravel 12)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)
[![OpenAPI/Swagger](https://img.shields.io/badge/Swagger-OpenAPI%203.0-green)](http://localhost:8000/api/documentation)

L'API **CRM-SKYNITY** est la solution de **Sales Force Automation (SFA)** et de gestion de la relation client spécialement conçue pour la promotion médicale, l'industrie pharmaceutique et la gestion commerciale terrain.

---

## 📑 Table des Matières
1. [Spécifications Métier & Workflow](#-spécifications-métier--workflow)
2. [Audit des Tâches Réalisées (Réponse à `taches_restantes_backend.md`)](#-audit-des-tâches-réalisées-réponse-à-taches_restantes_backendmd)
3. [Architecture & Modèle RBAC](#-architecture--modèle-rbac)
4. [Installation & Prise en Main](#-installation--prise-en-main)
5. [Documentation API Swagger Interactive](#-documentation-api-swagger-interactive)
6. [Seeders & Données de Test Autonomes](#-seeders--données-de-test-autonomes)
7. [Automatisations & Tâches en Arrière-plan](#-automatisations--tâches-en-arrière-plan)

---

## 📘 Spécifications Métier & Workflow

Le backend orchestre le cycle de vie complet des équipes terrain et des managers. Pour une analyse exhaustive du workflow et des schémas d'architecture, consultez :
* 📄 [`workflow_crm.md`](workflow_crm.md) : Spécification du Workflow & Architecture Métier du CRM.
* 📋 [`taches_restantes_backend.md`](taches_restantes_backend.md) : Cahier des charges et audit d'implémentation.

---

## ✅ Audit des Tâches Réalisées (Réponse à `taches_restantes_backend.md`)

Toutes les exigences identifiées dans le document [`taches_restantes_backend.md`](taches_restantes_backend.md) ont été finalisées et intégrées :

### 1. Remplacement des Endpoints Mocks par des Algorithmes Réels
* ✅ **Optimisation de Tournée GPS (`POST /api/v1/tour-plans/optimize`)** :
  * Implémentation de l'**Algorithme du Plus Proche Voisin (*Nearest Neighbor*)** basé sur la formule Haversine ($6371 \text{ km}$).
  * Tri dynamique des activités par proximité GPS, calcul de la distance totale et estimation de la durée.
* ✅ **KPIs de Performance d'Équipe (`GET /api/v1/analytics/team-performance`)** :
  * Requête d'agrégation Eloquent calculant en temps réel pour chaque délégué de l'équipe : nombre de visites réalisées dans le mois, taux d'atteinte d'objectifs et chiffre d'affaires des commandes validées (`Order`).
* ✅ **Géolocalisation Temps Réel (`GET /api/v1/users/locations`)** :
  * Extraction des dernières coordonnées GPS enregistrées lors des Check-in/Check-out (`gps_debut_lat`, `gps_debut_lng`) des délégués.
* ✅ **Réinitialisation de Mot de Passe (`POST /api/v1/password/email`)** :
  * Ajout de la route publique et de la méthode `sendResetLinkEmail` avec génération de token temporaire stocké en cache.
* ✅ **Nettoyage des Données Statiques dans `OrderController` et `DocumentController`** :
  * Calcul dynamique de la somme des quantités d'articles commandés (`items()->sum('quantity')`).
  * Calcul de la taille réelle des fichiers documents (`Storage::size`) avec formatage en Mo.

---

## 🏰 Architecture & Modèle RBAC

### 1. Hiérarchie Territoriale (7 Niveaux)
```
Pays (Country) ──> Filiale (Subsidiary) ──> Région (Region) ──> Territoire (Territory) ──> Zone ──> Secteur ──> Équipe
```

### 2. Scopes & Matrice des 7 Rôles Systèmes (`Spatie Permission`)
| Rôle | Scope Spatie | Périmètre & Attributions |
| :--- | :--- | :--- |
| **`super-admin`** | `scope:global` | Configuration globale multi-pays, gestion des tenants, rôles et logs. |
| **`admin-pays`** | `scope:country` | Administration de la filiale, gestion des utilisateurs, territoires et catalogues. |
| **`dir-general`** | `scope:subsidiary` | Dashboard Exécutif BI, suivi du CA et taux d'atteinte des objectifs stratégiques. |
| **`dir-commercial`**| `scope:subsidiary` | Pilotage du pipeline d'opportunités, validation des remises et fixation des quotas. |
| **`dir-medical`** | `scope:subsidiary` | Gestion des KOL, validation des supports ADV et gouvernance scientifique. |
| **`manager`** | `scope:team` / `scope:region` | Validation des plannings (TourPlan), suivi GPS d'équipe et approbation des frais. |
| **`delegue`** | `scope:team` | Visites terrain, E-Detailing ADV, remise d'échantillons et prise de commandes. |

---

## ⚙️ Installation & Prise en Main

### 1. Prérequis
- **PHP** : `>= 8.2` (Extensions `pdo`, `mbstring`, `openssl`, `bcmath` requises)
- **Composer** : `>= 2.0`
- **Base de données** : MySQL / MariaDB / PostgreSQL

### 2. Configuration & Lancement
```bash
# 1. Cloner le projet et installer les dépendances
composer install

# 2. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 3. Exécuter les migrations et le jeu de données complet
php artisan migrate:fresh --seed

# 4. Démarrer le serveur local
php artisan serve
```

---

## 📖 Documentation API Swagger Interactive

L'API est entièrement documentée via **OpenAPI 3.0 / L5-Swagger**.

Accès à la documentation interactive :
👉 **[http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)**

Pour régénérer la documentation OpenAPI après modif des annotations :
```bash
php artisan l5-swagger:generate
```

---

## 🌾 Seeders & Données de Test Autonomes

Conformément à la Section 10 du cahier des charges, chaque domaine métier dispose de son seeder dédié exécutable séparément :

```bash
# Exécution d'un seeder spécifique
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=AccountSeeder
php artisan db:seed --class=ContactSeeder
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=ActivitySeeder
php artisan db:seed --class=StockSeeder
php artisan db:seed --class=EventSeeder
php artisan db:seed --class=ExpenseSeeder
php artisan db:seed --class=CampaignSeeder
php artisan db:seed --class=NotificationSeeder
```

### Ordre d'exécution automatique dans `DatabaseSeeder.php` :
1. `RoleAndPermissionSeeder` : Permissions de modules et 7 rôles natifs.
2. `SettingSeeder` : Paramètres système et branding.
3. `UserSeeder` : Comptes utilisateurs de test par rôle.
4. `AccountSeeder` : Hôpitaux, CHU, cliniques et pharmacies.
5. `ContactSeeder` : Professionnels de santé (HCP, RPPS).
6. `ProductSeeder` : Gamme de médicaments et posologies.
7. `ActivitySeeder` : Visites réalisées, planifiées et comptes-rendus.
8. `StockSeeder` : Mouvements d'échantillons et dotations.
9. `EventSeeder` : Congrès et réunions de staff.
10. `ExpenseSeeder` : Notes de frais avec pièces jointes.
11. `CampaignSeeder` & `NotificationSeeder`.

---

## ⚙️ Automatisations & Tâches en Arrière-plan

### 1. Cron Jobs Planifiés (`app/Console/Kernel.php`)
* **Rappels de Visite (`07h00`)** : Notification par e-mail des visites planifiées pour le lendemain.
* **Scoring Prospect (`02h00`)** : Recalcul automatique des profils cibles et potentiel des professionnels de santé (`php artisan app:calculate-scores`).

### 2. Observers Métiers (`Observers/`)
* **`ActivityObserver`** : Calcul automatique des durées, validation GPS (tolérance 500m), déduction d'échantillons (`SampleMovement`) lors du Check-out.
* **`CampaignObserver`** : Contrôle des règles de transition d'état des campagnes marketing.
