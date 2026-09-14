# Skynity CRM Backend (API)

Il s'agit de l'API de base (backend) pour le CRM, développée avec Laravel 12. 
Le backend repose sur une architecture agnostique, conçue pour gérer la force de vente (collaborateurs et agents terrain), les tournées, la gestion des stocks d'échantillons, les campagnes marketing, et bien plus encore.

## 🚀 Prérequis

- **PHP** : >= 8.2
- **Composer** : >= 2.0
- **Base de données** : MySQL ou PostgreSQL
- **Serveur web** : Nginx / Apache / Serveur interne PHP

## ⚙️ Installation & Configuration

1. **Cloner le dépôt et installer les dépendances**
   ```bash
   git clone <votre_depot>
   cd CRM-backend
   composer install
   ```

2. **Configurer l'environnement**
   Copiez le fichier d'exemple et configurez votre base de données :
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *N'oubliez pas de configurer vos identifiants de base de données dans le `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

3. **Construire la base de données et les données de test (Seeders)**
   Cette commande génère toute la structure (tables, UUID, clés étrangères) et peuple la base avec des rôles, des utilisateurs (`admin@crm.local`, `utilisateur@crm.local`), des filiales, des contacts professionnels et des historiques de visites.
   ```bash
   php artisan migrate:fresh --seed
   ```

4. **Lancer le serveur de développement**
   ```bash
   php artisan serve
   ```

## 📖 Documentation API (Swagger)

L'API est entièrement documentée avec L5-Swagger (OpenAPI 3). 
Une fois le serveur lancé, vous pouvez consulter la documentation et tester les endpoints interactivement à l'adresse suivante :

**[http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)**

*Pour régénérer la documentation après avoir modifié les annotations dans les contrôleurs, exécutez :*
```bash
php artisan l5-swagger:generate
```

## 🧠 Intelligence Métier (Tâches en arrière-plan)

Le CRM inclut des mécanismes automatiques (State Machines et CRON) pour assister les équipes.

### 1. Commandes Planifiées (Cron Jobs)
Assurez-vous que le planificateur de tâches de Laravel est configuré sur votre serveur de production (`* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1`).

*   **Rappels des visites (`app:send-reminders`)** : Tourne à **07h00** tous les jours. Envoie un récapitulatif par email aux collaborateurs pour les visites de la journée suivante.
*   **Moteur de Scoring (`app:calculate-scores`)** : Tourne à **02h00** tous les jours. Recalcule le `potentiel` et le `score` des contacts en fonction de la fréquence des visites (30 derniers jours).
    *   *Vous pouvez tester le calcul manuellement avec :* `php artisan app:calculate-scores`

### 2. Workflows (Observers)
*   **Workflow Visites (`ActivityObserver`)** :
    *   Lorsqu'une visite est marquée "Réalisée", le système calcule la durée automatiquement, vérifie le pointage GPS de l'activité par rapport à la cible (tolérance 500m), déduit les échantillons donnés de la table `sample_movements`, et verrouille la visite contre toute modification future.
*   **Workflow Campagnes (`CampaignObserver`)** :
    *   Empêche les sauts de statuts illogiques (`creation` -> `validation` -> `deploiement`...). Lève une erreur API HTTP 422 si la séquence métier n'est pas respectée.

## 🎨 Branding Dynamique (Nom, Logo, Favicon)

Le CRM est conçu pour être personnalisé en « marque blanche » par filiale.
La table `settings` (clé/valeur) stocke les entrées `app_name`, `logo_path` et `favicon_path`.
Chaque paramètre peut être global (filiale = null) ou spécifique à une filiale (prioritaire sur le global).

### Endpoints de branding

| Méthode | URL | Auth | Description |
|---------|-----|------|-------------|
| `GET` | `/api/v1/branding` | ❌ public | Récupère nom + URLs logo et favicon |
| `PUT` | `/api/v1/branding/name` | ✅ Bearer | Met à jour le nom de l'application |
| `POST` | `/api/v1/branding/logo` | ✅ Bearer | Upload / remplace le logo |
| `DELETE` | `/api/v1/branding/logo` | ✅ Bearer | Supprime le logo |
| `POST` | `/api/v1/branding/favicon` | ✅ Bearer | Upload / remplace la favicon |
| `DELETE` | `/api/v1/branding/favicon` | ✅ Bearer | Supprime la favicon |

### Contraintes de validation

| Fichier | Formats autorisés | Taille maximale | Recommandation |
|---------|-------------------|-----------------|----------------|
| **Logo** | `jpeg`, `jpg`, `png`, `webp` | **2 Mo** | Ratio 16:9 ou carré, min. 200 px |
| **Favicon** | `png`, `ico` | **512 Ko** | Carré 32×32 px ou 64×64 px |

- L'ancien fichier est automatiquement supprimé du stockage lors de chaque remplacement.
- Les URLs retournées par l'API sont des URLs absolues prêtes à l'emploi (pas de chemins relatifs).
- Le paramètre `subsidiary_id` (optionnel) permet de surcharger par filiale.

### Exemple — Récupérer le branding avant login

```http
GET /api/v1/branding
```
```json
{
  "app_name": "MyCRM Pro",
  "logo_url": "https://example.com/storage/branding/logo_AbCdEfGhIjKl.png",
  "favicon_url": "https://example.com/storage/branding/favicon_XyZwVuTs.ico"
}
```

### Exemple — Uploader un logo (curl)

```bash
curl -X POST https://example.com/api/v1/branding/logo \
  -H "Authorization: Bearer <TOKEN>" \
  -F "logo=@/path/to/logo.png" \
  -F "subsidiary_id=<uuid-facultatif>"
```

## 🌍 Gestion des Pays & Territoires (Multi-Pays)

L'API propose un CRUD complet pour piloter les pays d'implantation :
- **Endpoints disponibles** (`/api/v1/countries`) :
  - `GET /api/v1/countries` : Liste tous les pays avec leurs filiales rattachées.
  - `POST /api/v1/countries` : Créer un nouveau pays (`name`, `iso_code` unique, `currency`, `language`, `timezone`, `regulatory_settings`, `status`).
  - `GET /api/v1/countries/{id}` : Détails d'un pays avec ses filiales.
  - `PUT /api/v1/countries/{id}` : Modifier un pays.
  - `DELETE /api/v1/countries/{id}` : Supprimer un pays (protégé contre la suppression si des filiales y sont encore rattachées).

## 📦 Gestion des Stocks & Échantillons

Le module de stock assure la traçabilité complète des échantillons médicaux et dotations délégués (normes pharmaceutiques) :
- **État du stock délégué** : `GET /api/v1/my-stock` (calcul en temps réel des quantités disponibles, distribuées et alertes par lot).
- **Mouvements de stock (CRUD complet)** : `GET`, `POST`, `PUT`, `DELETE` sur `/api/v1/sample-movements` (et alias `/api/v1/stocks`).
  - **Création (`POST`)** : Entrée en stock, réapprovisionnement, attribution, distribution terrain.
  - **Modification (`PUT/PATCH`)** : Correction des quantités, lots ou dates de péremption.
  - **Suppression (`DELETE`)** : Annulation d'un mouvement avec réajustement automatique des stocks.
  - **Types de mouvements** : `reception` (entrée/réassort), `attribution`, `distribution`, `retour`, `destruction` (périmés/perte), `inventaire`.
  - **Traçabilité** : Numéro de lot (`lot_number`), date de péremption (`expiry_date`), signature et QR code.

## 🔐 Sécurité & Authentification

- **Authentification par Jetons (Token Bearer)** : L'API utilise **Laravel Sanctum** pour sécuriser tous les accès.
- **Gestion des Rôles & Permissions (RBAC)** : Assurée par **Spatie Permission**, organisée par modules métier (`super-admin`, `admin-pays`, `dir-general`, `manager`, `delegue`, etc.).
- **Création d'utilisateurs & Mot de passe temporaire** :
  - Lors de la création d'un utilisateur (`POST /api/v1/users`), un mot de passe temporaire aléatoire et robuste est généré automatiquement.
  - Un email d'invitation contenant ses identifiants lui est envoyé.
  - À sa première connexion (`POST /api/v1/login`), le système renvoie un statut `requires_password_change` (HTTP 403). L'utilisateur doit obligatoirement définir son propre mot de passe (min. 8 caractères via `POST /api/v1/auth/change-password`).
- **Authentification à double facteur (OTP) optionnelle par utilisateur** :
  - **Par défaut (`otp_enabled = false`)** : L'OTP n'est **pas requis**. Dès que l'utilisateur a changé son mot de passe temporaire (ou lors de ses connexions régulières), il reçoit directement son Token d'authentification et est connecté sans friction.
  - **Activation individuelle** : L'utilisateur peut activer ou désactiver lui-même l'exigence d'un code OTP à 6 chiffres via l'endpoint protégé `POST /api/v1/auth/toggle-otp` (ou lors de la modification de son profil).
  - **Si activé (`otp_enabled = true`)** : Un code OTP est généré, stocké en cache (valable 10 minutes) et envoyé par email (`POST /api/v1/verify-otp` nécessaire pour obtenir le Token).
- **Cloisonnement géographique** : Les données sont cloisonnées par périmètre (Pays/Filiale/Région/Secteur) via le trait global `HasPerimeter`.

## 🧪 Tests manuels rapides

Si vous avez lancé les Seeders, voici les identifiants de test préconfigurés :
- **Super Admin** : `admin@crm.local` (Mot de passe : `password`)
- **Agent Terrain / Collaborateur** : `delegue@crm.local` (Mot de passe : `password`)

*(Note : à la toute première connexion, l'API renverra un statut `requires_password_change` pour simuler le changement de mot de passe obligatoire).*
