# Guide E-Detailing - CRM Médical

## Vue d'ensemble

L'E-Detailing (Electronic Detailing) est une fonctionnalité qui permet aux délégués médicaux de présenter des supports visuels (PDF, vidéos, présentations) aux médecins lors de leurs visites. Le système trace les statistiques de ces présentations pour analyser l'efficacité.

---

## Structure des tables

### 1. Table `documents`

Cette table stocke tous les documents du système, y compris les supports E-Detailing.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | UUID | Identifiant unique du document |
| `subsidiary_id` | UUID (FK) | Filiale à laquelle le document est rattaché |
| `organizational_unit_id` | UUID (FK) | Unité organisationnelle (pour le filtrage par périmètre) |
| `speciality_id` | UUID (FK) | Spécialité médicale cible (ex: cardiologie) |
| `role_id` | UUID (FK) | Rôle cible (ex: délégué médical) |
| `documentable_id` | UUID | ID de l'entité liée (polymorphisme) |
| `documentable_type` | STRING | Type de l'entité (ex: `App\Models\Product`) |
| `title` | STRING | Titre du document |
| `description` | TEXT | Description du contenu |
| `type` | ENUM | Type : `pdf`, `video`, `presentation`, `image`, `other` |
| `file_path` | STRING | Chemin de stockage du fichier |
| `version` | STRING | Version du document (défaut: 1.0) |
| `is_active` | BOOLEAN | Document actif ou non |
| `expiration_date` | DATE | Date d'expiration du document |
| `status` | ENUM | Statut : `draft`, `review`, `approved`, `expired` |
| `created_at` | TIMESTAMP | Date de création |
| `updated_at` | TIMESTAMP | Date de modification |
| `deleted_at` | TIMESTAMP | Soft delete |

**Exemple de document E-Detailing :**
```json
{
  "id": "doc-uuid-123",
  "title": "CardioPulse 50mg - Présentation Tablet",
  "type": "presentation",
  "file_path": "storage/documents/cardio_pulse_preso.pdf",
  "documentable_type": "App\\Models\\Product",
  "documentable_id": "product-uuid-456",
  "speciality_id": "cardiology-uuid",
  "status": "approved",
  "is_active": true
}
```

---

### 2. Table `clm_presentation_analytics`

Cette table enregistre les statistiques de chaque présentation E-Detailing faite lors d'une visite.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | UUID | Identifiant unique de l'analytique |
| `activity_id` | UUID (FK) | Visite/activité associée |
| `document_id` | UUID (FK) | Document présenté |
| `user_id` | UUID (FK) | Délégué qui a fait la présentation |
| `contact_id` | UUID (FK) | Médecin/contact à qui la présentation a été faite |
| `total_duration_seconds` | INTEGER | Durée totale de la présentation en secondes |
| `slides_data` | JSON | Données détaillées sur les slides vues |
| `completed` | BOOLEAN | Si la présentation a été complétée |
| `created_at` | TIMESTAMP | Date de création |
| `updated_at` | TIMESTAMP | Date de modification |

**Exemple d'enregistrement d'analytique :**
```json
{
  "id": "analytic-uuid-789",
  "activity_id": "activity-uuid-101",
  "document_id": "doc-uuid-123",
  "user_id": "delegate-uuid-202",
  "contact_id": "contact-uuid-303",
  "total_duration_seconds": 245,
  "slides_data": {
    "slides": [
      { "slide_id": 1, "duration": 30, "viewed": true },
      { "slide_id": 2, "duration": 45, "viewed": true },
      { "slide_id": 3, "duration": 60, "viewed": true },
      { "slide_id": 4, "duration": 110, "viewed": false }
    ]
  },
  "completed": true
}
```

---

## Workflow E-Detailing

### Étape 1 : Création du document (par l'admin/manager)

1. L'administrateur ou le manager crée un document E-Detailing
2. Le document est associé à :
   - Un produit (via le polymorphisme `documentable`)
   - Une spécialité médicale cible
   - Une unité organisationnelle
   - Un rôle cible (ex: délégué médical)

**API Endpoint :**
```
POST /api/v1/documents
```

**Payload :**
```json
{
  "title": "CardioPulse 50mg - Présentation",
  "type": "presentation",
  "file_path": "path/to/file.pdf",
  "documentable_type": "App\\Models\\Product",
  "documentable_id": "product-uuid",
  "speciality_id": "speciality-uuid",
  "organizational_unit_id": "unit-uuid",
  "role_id": "delegue-role-uuid",
  "status": "approved"
}
```

---

### Étape 2 : Accès aux documents par le délégué

Le délégué peut récupérer la liste des documents E-Detailing disponibles pour lui :
- Filtrés par son unité organisationnelle
- Filtrés par son rôle
- Filtrés par la spécialité du contact visité

**API Endpoint :**
```
GET /api/v1/documents?type=presentation&is_active=true
```

---

### Étape 3 : Présentation lors d'une visite

Lors d'une visite, le délégué :
1. Sélectionne un document E-Detailing
2. Lance la présentation sur sa tablette
3. Le système enregistre le début de la présentation

**Enregistrement des analytics :**
```
POST /api/v1/activities/{activity_id}/clm-analytics
```

**Payload :**
```json
{
  "document_id": "doc-uuid-123",
  "contact_id": "contact-uuid-303",
  "slides_data": {
    "slides": [
      { "slide_id": 1, "duration": 30, "viewed": true }
    ]
  },
  "completed": false
}
```

---

### Étape 4 : Fin de la présentation

Lorsque le délégué termine la présentation :
1. Le système calcule la durée totale
2. Met à jour les données des slides
3. Marque la présentation comme complétée

**Mise à jour des analytics :**
```
PUT /api/v1/clm-analytics/{analytic_id}
```

**Payload :**
```json
{
  "total_duration_seconds": 245,
  "slides_data": {
    "slides": [
      { "slide_id": 1, "duration": 30, "viewed": true },
      { "slide_id": 2, "duration": 45, "viewed": true },
      { "slide_id": 3, "duration": 60, "viewed": true },
      { "slide_id": 4, "duration": 110, "viewed": false }
    ]
  },
  "completed": true
}
```

---

### Étape 5 : Analyse des performances

Le superviseur peut consulter les statistiques E-Detailing :
- Nombre de présentations par document
- Durée moyenne des présentations
- Taux de complétion
- Performance par délégué

**API Endpoint :**
```
GET /api/v1/analytics/edetailing-summary
```

**Réponse :**
```json
[
  {
    "document_id": "doc-uuid-123",
    "document_name": "CardioPulse 50mg - Présentation",
    "views_count": 45,
    "average_duration_seconds": 210,
    "completion_rate": 0.87
  }
]
```

---

## Relations entre les tables

```
┌─────────────────┐
│   documents     │
├─────────────────┤
│ id (PK)         │
│ title           │
│ type            │
│ file_path       │
│ ...             │
└────────┬────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────────────────┐
│ clm_presentation_analytics  │
├─────────────────────────────┤
│ id (PK)                     │
│ document_id (FK)            │───┐
│ activity_id (FK)            │   │
│ user_id (FK)                │   │
│ contact_id (FK)             │   │
│ total_duration_seconds      │   │
│ slides_data (JSON)          │   │
│ completed                   │   │
└─────────────────────────────┘   │
         │                         │
         │ N:1                     │
         │                         │
         ▼                         │
┌─────────────────┐               │
│   activities    │               │
├─────────────────┤               │
│ id (PK)         │               │
│ user_id         │               │
│ contact_id      │               │
│ date_activite   │               │
│ ...             │               │
└─────────────────┘               │
                                    │
                                    │ N:1
                                    │
                                    ▼
                         ┌─────────────────┐
                         │   contacts      │
                         ├─────────────────┤
                         │ id (PK)         │
                         │ name            │
                         │ speciality_id   │
                         │ ...             │
                         └─────────────────┘
```

---

## Permissions requises

Les permissions suivantes sont nécessaires pour gérer l'E-Detailing :

| Permission | Description | Rôles typiques |
|------------|-------------|----------------|
| `documents.view` | Voir les documents | Tous les rôles |
| `documents.view-edetailing` | Voir les documents E-Detailing | Délégués, Managers |
| `documents.create` | Créer des documents | Admin, Managers |
| `documents.edit` | Modifier des documents | Admin, Managers |
| `documents.delete` | Supprimer des documents | Admin |
| `analytics.view-edetailing` | Voir les analytics E-Detailing | Managers, Admin |

---

## Bonnes pratiques

### 1. Organisation des documents
- Associer chaque document E-Detailing à un produit spécifique
- Filtrer par spécialité médicale pour personnaliser les présentations
- Utiliser le champ `expiration_date` pour les documents avec des données périmées

### 2. Suivi des présentations
- Enregistrer chaque slide vue avec sa durée
- Marquer les présentations non complétées pour identifier les problèmes
- Corréler les analytics avec les résultats de visite (ventes, prescriptions)

### 3. Performance
- Optimiser les requêtes analytics avec des index sur `document_id` et `user_id`
- Utiliser le cache pour les rapports fréquemment consultés
- Archiver les anciennes analytics après une certaine période

---

## Exemples de code

### Créer un document E-Detailing

```php
use App\Models\Document;
use App\Models\Product;

$product = Product::where('code', 'CARDIO50')->first();

$document = Document::create([
    'title' => 'CardioPulse 50mg - Présentation Tablet',
    'type' => 'presentation',
    'file_path' => 'storage/documents/cardio_pulse_preso.pdf',
    'documentable_type' => Product::class,
    'documentable_id' => $product->id,
    'speciality_id' => $cardiologySpeciality->id,
    'organizational_unit_id' => $unit->id,
    'role_id' => $delegueRole->id,
    'status' => 'approved',
    'is_active' => true,
]);
```

### Enregistrer une présentation

```php
use App\Models\ClmPresentationAnalytic;
use App\Models\Activity;

$activity = Activity::find($activityId);

$analytic = ClmPresentationAnalytic::create([
    'activity_id' => $activity->id,
    'document_id' => $document->id,
    'user_id' => auth()->id(),
    'contact_id' => $activity->contact_id,
    'total_duration_seconds' => 245,
    'slides_data' => [
        'slides' => [
            ['slide_id' => 1, 'duration' => 30, 'viewed' => true],
            ['slide_id' => 2, 'duration' => 45, 'viewed' => true],
            ['slide_id' => 3, 'duration' => 60, 'viewed' => true],
        ]
    ],
    'completed' => true,
]);
```

### Récupérer les analytics d'un document

```php
use App\Models\ClmPresentationAnalytic;

$analytics = ClmPresentationAnalytic::with(['user', 'contact', 'document'])
    ->where('document_id', $documentId)
    ->get();

foreach ($analytics as $analytic) {
    echo "Présenté par {$analytic->user->name} ";
    echo "à {$analytic->contact->name} ";
    echo "durée: {$analytic->total_duration_seconds}s\n";
}
```

---

## Dépannage

### Problème : Les documents ne s'affichent pas pour le délégué

**Solution :**
- Vérifier que le document a le bon `organizational_unit_id`
- Vérifier que le document a le bon `role_id`
- Vérifier que `is_active` est à `true`
- Vérifier que le statut est `approved`

### Problème : Les analytics ne sont pas enregistrés

**Solution :**
- Vérifier que l'activité existe
- Vérifier que le document existe
- Vérifier que l'utilisateur a la permission `documents.view-edetailing`

### Problème : Les slides_data ne sont pas sauvegardés

**Solution :**
- Vérifier que le champ est envoyé en format JSON valide
- Vérifier que la colonne `slides_data` accepte le type JSON dans la base de données

---

## Conclusion

L'E-Detailing dans ce CRM est une fonctionnalité puissante qui permet :
- Aux délégués de présenter des supports visuels professionnels
- Aux managers de suivre l'efficacité des présentations
- À l'entreprise d'optimiser son contenu marketing

Le système est flexible grâce au polymorphisme et au filtrage par périmètre organisationnel.
