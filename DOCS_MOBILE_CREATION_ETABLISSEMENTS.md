# 📱 Spécifications Techniques & Guide d'Intégration Mobile
## Module : Création & Synchronisation d'Établissements (Comptes / Pharmacies)

---

> ### 💬 PROMPT PRÊT À L'EMPLOI POUR LE DÉVELOPPEUR MOBILE / IA MOBILE
>
> **Titre** : Mise à jour de la création des Établissements (Ajout obligatoire du Type d'Établissement `account_type_id`)
>
> **Contexte & Consigne** :
> "Auparavant, lors de la création d'un établissement sur l'application mobile, nous n'incluions pas le **Type d'Établissement** (`account_type_id`). Il est désormais impératif d'intégrer cette fonctionnalité.
>
> **Ce qui doit être fait sur le mobile** :
> 1. **Récupération des Types** : Lors de l'initialisation de l'application ou à l'ouverture du formulaire, effectuer un `GET /api/v1/configurations` (ou `/account-types`) pour récupérer la liste dynamique des types d'établissements (`id`, `libelle`, `code`).
> 2. **Formulaire de Création** : Ajouter un composant de sélection (Dropdown / Picker / Select recherchable) obligatoire pour le type d'établissement.
> 3. **Payload `POST /api/v1/accounts`** : Inclure le champ `"account_type_id": "<UUID_SELECTIONNE>"` dans la requête JSON.
> 4. **Synchronisation & Offline** : Enregistrer le `account_type_id` en local (SQLite / Hive / Room) en cas de mode hors-ligne avant la synchronisation.
>
> La documentation technique détaillée avec le schéma de base de données, les payloads JSON et la gestion du statut de validation est disponible ci-dessous."

---

### 📋 Vue d'ensemble Architecture & Base de Données

Dans le CRM, un établissement (Pharmacie, CHU, Clinique, Grossiste, etc.) est représenté en base de données par la table **`accounts`**.

* **Nom de la table** : `accounts`
* **Modèle Eloquent** : `App\Models\Account`
* **Clé Primaire** : `id` (UUIDv4)
* **Relations Clés** :
  * `account_type_id` : Clé étrangère (UUID) vers `account_types.id`.
  * `country_id` : Clé étrangère (UUID) vers `countries.id`.
  * `user_id` : Clé étrangère (UUID) du créateur (`users.id`).
  * `delegue_id` : Clé étrangère (UUID) du délégué référent (`users.id`).
  * `potential_id` : Clé étrangère (UUID) issue du mapping du potentiel (`LOW`, `MED`, `HIGH`).

---

### 🔑 1. En-têtes HTTP Requis (Authentification)

Chaque requête envoyée par l'application mobile doit comporter :

```http
Authorization: Bearer <TOKEN_SANCTUM_DELEGUE>
Accept: application/json
Content-Type: application/json
```

---

### 📥 2. Étape 1 : Récupérer les Types d'Établissements (`account_types`)

Pour que l'utilisateur mobile puisse sélectionner un **Type d'Établissement** (Pharmacie, CHU, Clinique, Cabinet, Grossiste, etc.), l'application doit d'abord récupérer les configurations.

* **Endpoint** : `GET /api/v1/configurations` (ou `GET /api/v1/account-types`)
* **Méthode** : `GET`

#### Exemple de Réponse JSON :
```json
{
  "accountTypes": [
    {
      "id": "9b1deb4d-3b7d-416b-9c98-123456789abc",
      "code": "PHA",
      "libelle": "Pharmacie"
    },
    {
      "id": "9b1deb4d-3b7d-416b-9c98-987654321xyz",
      "code": "HOP",
      "libelle": "Hôpital / CHU"
    },
    {
      "id": "9b1deb4d-3b7d-416b-9c98-456789123def",
      "code": "CLI",
      "libelle": "Clinique Privée"
    }
  ]
}
```

---

### 📤 3. Étape 2 : Envoyer la Création d'Établissement

* **Endpoint** : `POST /api/v1/accounts`
* **Méthode** : `POST`

#### 📝 Payload JSON Complet (Body) :

```json
{
  "nom": "Pharmacie de la Paix",
  "account_type_id": "9b1deb4d-3b7d-416b-9c98-123456789abc",
  "ville": "Lomé",
  "adresse": "Avenue de la Chance, Quartier Totsi",
  "quartier": "Totsi",
  "potentiel": "fort",
  "gps_lat": 6.1375421,
  "gps_lng": 1.2125104,
  "telephone": "+22890000000",
  "email": "contact@pharmaciepaix.com",
  "responsable": "Dr. Jean KOFFI",
  "contact_ids": [],
  "delegue_ids": [],
  "local_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
}
```

---

### 🔍 4. Dictionnaire du Schéma de Données (Base de Données)

| Champ JSON | Colonne DB (`accounts`) | Type Data | Requis ? | Description & Directives |
| :--- | :--- | :--- | :--- | :--- |
| `nom` | `nom` | String(255) | **OUI** | Nom de la structure. *Note: Si le type est Pharmacie, le backend préfixera automatiquement `"PHARMACIE "` si absent.* |
| `account_type_id` | `account_type_id` | UUID | **OUI** | ID de la table `account_types`. **Ne pas envoyer de code brut, envoyer le UUID**. |
| `ville` | `ville` | String(100) | **OUI** | Ville ou commune principale (ex: `"Lomé"`). |
| `adresse` | `adresse` | Text | Non | Adresse physique ou repère. |
| `quartier` | `quartier` | String(100) | Non | Quartier ou zone d'implantation. |
| `potentiel` | `potentiel` | Enum | Non | Enum acceptant uniquement : `"faible"`, `"moyen"`, `"fort"`. |
| `gps_lat` | `gps_lat` | Decimal(10,7) | Non | Latitude GPS issue du capteur du téléphone (ex: `6.1375421`). |
| `gps_lng` | `gps_lng` | Decimal(10,7) | Non | Longitude GPS issue du capteur du téléphone (ex: `1.2125104`). |
| `telephone` | `telephone` | String(30) | Non | Numéro de téléphone principal. |
| `email` | `email` | String(255) | Non | Adresse email de contact. |
| `responsable` | `responsable` | String(255) | Non | Nom du titulaire / responsable de la structure. |
| `contact_ids` | Table pivot | Array[UUID] | Non | Liste d'UUIDs de prescripteurs/médecins à rattacher. |
| `delegue_ids` | Table `delegue_id` | Array[UUID] | Non | Si créé par un Délégué : laisser vide `[]` (le backend s'affecte automatiquement). |
| `local_uuid` | `local_uuid` | UUID | **Recommandé** | UUIDv4 généré localement sur le mobile pour le mode **Offline-First**. |

---

### ⚙️ 5. Rôles & Workflow de Validation Automatique

* **Si l'utilisateur connecté est un `DELEGUE_MEDICAL`** :
  * Le serveur attribue automatiquement :
    * `statut` = `"en_attente"`
    * `statut_validation` = `"en_attente"`
    * `delegue_id` = ID de l'utilisateur connecté.
  * Une notification est automatiquement envoyée à son superviseur pour validation.
* **Si l'utilisateur connecté est un `SUPERVISEUR` ou `ADMIN`** :
  * Le serveur valide directement :
    * `statut` = `"actif"`
    * `statut_validation` = `"valide"`

---

### ✅ 6. Réponse Réussie du Serveur (`201 Created`)

```json
{
  "data": {
    "id": "7a9b0c1d-2e3f-4a5b-6c7d-8e9f0a1b2c3d",
    "nom": "PHARMACIE DE LA PAIX",
    "account_type_id": "9b1deb4d-3b7d-416b-9c98-123456789abc",
    "account_type": {
      "id": "9b1deb4d-3b7d-416b-9c98-123456789abc",
      "code": "PHA",
      "libelle": "Pharmacie"
    },
    "ville": "Lomé",
    "adresse": "Avenue de la Chance, Quartier Totsi",
    "potentiel": "fort",
    "statut": "en_attente",
    "statut_validation": "en_attente",
    "gps_lat": 6.1375421,
    "gps_lng": 1.2125104,
    "local_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "created_at": "2026-10-06T20:12:00Z"
  }
}
```

---

### ❌ 7. Erreurs de Validation (`422 Unprocessable Entity`)

Si un champ obligatoire manque ou est invalide :

```json
{
  "message": "Données de validation invalides",
  "errors": {
    "account_type_id": [
      "Le champ type d'établissement est obligatoire ou invalide."
    ],
    "nom": [
      "Le champ nom est obligatoire."
    ]
  }
}
```

---

### 📱 8. Consignes Spécifiques pour l'Application Mobile (Offline & Geolocation)

1. **Sélecteur Type Établissement** :
   * Afficher un Dropdown/Picker obligatoire basé sur la liste renvoyée par `GET /api/v1/configurations`.
   * Stocker les `account_types` localement dans la base SQLite/Room/Hive du mobile.

2. **Capteur GPS** :
   * Activer le GPS du téléphone pour alimenter automatiquement `gps_lat` et `gps_lng`.

3. **Génération de `local_uuid` (Offline First)** :
   * Générer un `UUIDv4` lors de la création sur le téléphone.
   * Si l'appareil est hors-ligne, sauvegarder en local avec `local_uuid`.
   * Dès le retour du réseau internet, envoyer la requête au serveur avec le `local_uuid` pour éviter tout doublon de création.
