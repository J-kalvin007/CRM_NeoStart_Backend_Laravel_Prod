# Skynity CRM Backend (API)

Il s'agit de l'API de base (backend) pour le CRM Skynity, développée avec Laravel 11. 
Le backend repose sur une architecture agnostique, conçue pour gérer la force de vente terrain (Délégués médicaux/commerciaux), les tournées, la gestion des stocks d'échantillons, les campagnes marketing, et bien plus encore.

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
   Cette commande génère toute la structure (tables, UUID, clés étrangères) et peuple la base avec des rôles, des utilisateurs (`admin@crm.local`, `delegue@crm.local`), des filiales, des médecins et des historiques de visites.
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

*   **Rappels des visites (`app:send-reminders`)** : Tourne à **07h00** tous les jours. Envoie un récapitulatif par email aux délégués pour les visites de la journée suivante.
*   **Moteur de Scoring (`app:calculate-scores`)** : Tourne à **02h00** tous les jours. Recalcule le `potentiel` et le `score` des contacts en fonction de la fréquence des visites (30 derniers jours).
    *   *Vous pouvez tester le calcul manuellement avec :* `php artisan app:calculate-scores`

### 2. Workflows (Observers)
*   **Workflow Visites (`ActivityObserver`)** :
    *   Lorsqu'une visite est marquée "Réalisée", le système calcule la durée automatiquement, vérifie le pointage GPS de l'activité par rapport à la cible (tolérance 500m), déduit les échantillons donnés de la table `sample_movements`, et verrouille la visite contre toute modification future.
*   **Workflow Campagnes (`CampaignObserver`)** :
    *   Empêche les sauts de statuts illogiques (`creation` -> `validation` -> `deploiement`...). Lève une erreur API HTTP 422 si la séquence métier n'est pas respectée.

## 🔐 Sécurité & Authentification

-   L'API utilise **Laravel Sanctum** pour l'authentification par jeton (Token Bearer).
-   La gestion des rôles et des accès (RBAC) est assurée par **Spatie Permission** (`super-admin`, `manager`, `delegue`).
-   Les données sont cloisonnées par périmètre géographique (Filiale/Pays) via le trait global `HasPerimeter`.

## 🧪 Tests manuels rapides

Si vous avez lancé les Seeders, voici les identifiants de test préconfigurés :
- **Super Admin** : `admin@crm.local` (Mot de passe : `password`)
- **Délégué Médical** : `delegue@crm.local` (Mot de passe : `password`)

*(Note : à la toute première connexion, l'API renverra un statut `force_password_change` pour simuler le changement de mot de passe obligatoire).*
