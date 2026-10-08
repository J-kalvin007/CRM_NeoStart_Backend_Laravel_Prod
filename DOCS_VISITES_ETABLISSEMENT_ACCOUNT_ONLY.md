# 📱 Spécifications Techniques & Guide d'Intégration
## Module : Visites d'Établissements sans Prescripteur (`is_account_only`)

---

> ### 💬 PROMPT PRÊT À L'EMPLOI POUR LE DÉVELOPPEUR MOBILE / FRONTEND / IA
>
> **Titre** : Intégration de la configuration dynamique des visites ciblées sur établissement (Pharmacies, Grossistes, Officines...) via le drapeau `is_account_only`
>
> **Contexte & Consigne** :
> "Dans le CRM, certaines visites ne ciblent pas un médecin prescripteur individuel, mais directement un établissement (par exemple une Pharmacie, une Officine, un Grossiste ou un Dépôt). Pour ces établissements, la configuration système définit `is_account_only = true`.
>
> **Ce qui doit être implémenté sur le client (Mobile / Web)** :
> 1. **Récupération de la Configuration** : Lors du chargement des types d'établissements (`GET /api/v1/account-types` ou `GET /api/v1/configurations`), vérifier la propriété `is_account_only` (booléen).
> 2. **Formulaire de Création de Visite** :
>    - Lorsque l'utilisateur sélectionne un établissement ayant `is_account_only === true`, **masquer/désactiver la recherche et la sélection de prescripteurs (médecins)**.
>    - Le champ `account_id` devient l'unique cible obligatoire de la visite (`contact_id` est envoyé à `null` ou `undefined`).
>    - Si des produits sont rattachés à cet établissement, les pré-sélectionner par défaut.
> 3. **Affichage dans l'Agenda et les Listes de Visites** :
>    - Lorsque `is_account_only === true` ou lorsque `contact_id` est `null`, afficher le **Nom de l'Établissement** (ex: *PHARMACIE EMMANUEL*) à la place du nom du médecin dans les titres d'événements et les cartes de visite.
>
> Vous trouverez ci-dessous la documentation technique détaillée avec le schéma de base de données, les endpoints d'API et les exemples de payloads JSON."

---

### 📋 1. Concept Fonctionnel & Architecture

Certaines structures de santé et points de distribution ne nécessitent pas la saisie d'un médecin prescripteur lors d'une visite de promotion ou de suivi commercial :
* **Pharmacies & Officines**
* **Grossistes & Répartiteurs**
* **Dépôts Pharmaceutiques**
* **Centres de Santé sans médecin attitré**

Pour automatiser ce comportement, chaque type d'établissement (`AccountType`) possède un attribut booleen `is_account_only` :
* `is_account_only = false` (par défaut) : Visite classique nécessitant la sélection d'un ou plusieurs médecins prescripteurs (`contact_id`).
* `is_account_only = true` : Visite ciblant directement l'établissement (`account_id`), sans prescripteur requis.

---

### 🗄️ 2. Base de Données & Modèles Eloquent

#### Table `account_types`
| Colonne | Type | Description |
| :--- | :--- | :--- |
| `id` | `UUID` (Primary) | Identifiant unique du type d'établissement |
| `code` | `VARCHAR(50)` | Code unique (ex: `pharmacie`, `grossiste`, `hopital`) |
| `libelle` | `VARCHAR(255)` | Libellé affiché à l'utilisateur (ex: *Pharmacie / Officine*) |
| `is_account_only` | `BOOLEAN` | `true` si les visites de cet établissement se font sans prescripteur |
| `is_active` | `BOOLEAN` | Statut d'activation du type d'établissement |

#### Pivot `account_type_activity_type`
Associe les types d'activités autorisées pour chaque type d'établissement (ex: *Visite Officine*, *Prise de commande*, *E-Posting*).

#### Table `activities` (Visites terrain)
| Colonne | Type | Description |
| :--- | :--- | :--- |
| `id` | `UUID` (Primary) | Identifiant unique de la visite |
| `account_id` | `UUID` (Nullable) | Identifiant de l'établissement cible (`accounts.id`) |
| `contact_id` | `UUID` (Nullable) | Identifiant du médecin prescripteur (`contacts.id`). `null` si `is_account_only = true` |
| `user_id` | `UUID` | Délégué réalisant la visite |
| `activity_type` | `VARCHAR(50)` | Type d'activité (ex: `visite`, `visite_officine`) |
| `date_activite` | `DATE` | Date de la visite (`YYYY-MM-DD`) |
| `heure_debut` | `TIME` | Heure prévue (`HH:MM`) |
| `statut` | `VARCHAR(50)` | `brouillon`, `planifiee`, `en_cours`, `realisee`, `manquee`, `annulee` |
| `meta` | `JSON` | Métadonnées additionnelles (`is_account_only`, `campaign_id`, etc.) |

---

### 📡 3. Endpoints API Backend

#### A. Récupération des Types d'Établissements & Configurations
```http
GET /api/v1/account-types
GET /api/v1/configurations
Authorization: Bearer <TOKEN>
```

**Exemple de Réponse JSON (`200 OK`)** :
```json
{
  "data": [
    {
      "id": "01a11ae4-699d-719b-bc30-4506e4c08a59",
      "code": "pharmacie",
      "libelle": "Pharmacie / Officine",
      "is_account_only": true,
      "is_active": true,
      "activity_types": [
        {
          "id": "01a11aea-1c98-727a-806e-f0624a6ed441",
          "code": "visite_officine",
          "name": "Visite Officine"
        }
      ]
    },
    {
      "id": "01a11ae4-777a-719b-bc30-4506e4c08a99",
      "code": "hopital",
      "libelle": "Hôpital / CHU",
      "is_account_only": false,
      "is_active": true,
      "activity_types": []
    }
  ]
}
```

---

#### B. Gestion des Types d'Établissements (Admin / Configuration)
```http
POST /api/v1/account-types
PUT /api/v1/account-types/{id}
Content-Type: application/json
Authorization: Bearer <TOKEN>
```

**Payload Request** :
```json
{
  "code": "grossiste",
  "libelle": "Grossiste Répartiteur",
  "is_account_only": true,
  "is_active": true,
  "activity_type_ids": [
    "01a11aea-1c98-727a-806e-f0624a6ed441"
  ]
}
```

---

#### C. Création d'une Visite d'Établissement (sans prescripteur)
```http
POST /api/v1/activities
Content-Type: application/json
Authorization: Bearer <TOKEN>
```

**Payload Request (Visite d'Officine / Pharmacie - `is_account_only = true`)** :
```json
{
  "activity_type": "Visite Officine",
  "activity_type_id": "01a11aea-1c98-727a-806e-f0624a6ed441",
  "date_activite": "2026-10-10",
  "heure_debut": "09:00",
  "account_id": "01a0c97c-4a7b-7327-b592-6ab8e08fdf7f",
  "contact_id": null,
  "notes": "Objectif: Réapprovisionnement de stock",
  "statut": "brouillon",
  "products": [
    {
      "product_id": "01a0f2d7-1ad0-70dd-8491-1dfb0ddf49ba",
      "quantity": 10,
      "customer_interest": "fort",
      "remarques": "Commande urgente"
    }
  ],
  "meta": {
    "is_account_only": true,
    "activity_key": "visite_officine"
  }
}
```

---

### 🎨 4. Guide d'Implémentation Frontend & Mobile

#### 1. Formulaire de Création de Visite
* Écouter le changement de sélection de l'établissement (`account_id`).
* Récupérer le `account_type` associé à cet établissement.
* Si `account_type.is_account_only === true` :
  1. Vider et cacher le champ de recherche de médecin/prescripteur (`doctorIds = []`).
  2. Activer l'obligation de l'établissement (`account_id`).
  3. Charger automatiquement les produits associés à l'établissement (s'il y en a) dans la section des produits présentés.

#### 2. Affichage du Titre de la Visite (Cartes, Agenda, Listes)
Utiliser la logique universelle de résolution du nom de cible :

```typescript
function getVisitDisplayTitle(visit: any): string {
  // 1. Si prescripteur présent -> Nom du médecin
  if (visit.contact && (visit.contact.nom_complet || visit.contact.nom)) {
    return visit.contact.nom_complet || `${visit.contact.prenom || ''} ${visit.contact.nom}`.trim();
  }
  
  // 2. Si établissement présent -> Nom de l'établissement
  if (visit.account && visit.account.nom) {
    return visit.account.nom;
  }
  
  // 3. Fallback sur le titre de la visite ou établissement direct
  return visit.establishmentName || visit.title || 'Visite Établissement';
}
```

#### 3. Soumission d'un Brouillon de Visite
* Lorsque le délégué valide sa visite planifiée, envoyer :
```http
PUT /api/v1/activities/{id}
Content-Type: application/json

{
  "statut": "planifiee"
}
```
* La visite passe du statut `brouillon` (privé au délégué) au statut `planifiee` (visible par le Super Admin, Superviseur et Manager).

---

### ✅ 5. Synthèse des Règles Métier

1. **Confidentialité des Brouillons** : Une visite au statut `brouillon` est uniquement visible par le délégué qui l'a créée (`user_id`). Elle ne s'affiche chez les responsables/Super Admins qu'une fois soumise (`planifiee`, `en_attente`, `en_cours`, `realisee`, etc.).
2. **Pas de Prescripteur Réclame** : Pour un établissement `is_account_only = true`, l'API accepte `contact_id = null`.
3. **Affichage Établissement** : Les composants d'agenda et de liste affichent toujours le nom de l'établissement en lieu et place du médecin quand `contact_id` est nul ou quand l'établissement est de type `is_account_only`.
