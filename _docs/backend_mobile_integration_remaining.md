# Fonctionnalités backend restantes pour le mobile

Date : 16 septembre 2026

Ce document recense uniquement les travaux à réaliser côté backend Laravel pour que le backend communique correctement avec l'application mobile. Il ne décrit pas les modifications Flutter déjà effectuées.

## 1. Contrat de synchronisation offline

### `GET /api/v1/sync/pull`

Le backend doit retourner, dans une enveloppe stable :

```json
{
  "timestamp": "2026-09-16T12:00:00Z",
  "data": {
    "contacts": [],
    "accounts": [],
    "products": [],
    "activities": [],
    "tour_plans": [],
    "tasks": [],
    "documents": [],
    "digital_supports": [],
    "events": [],
    "notifications": [],
    "sample_movements": [],
    "campaigns": [],
    "opportunities": [],
    "orders": [],
    "sales": [],
    "expenses": [],
    "leaves": [],
    "trainings": [],
    "conversations": []
  },
  "deleted": {
    "contacts": [],
    "accounts": [],
    "activities": [],
    "documents": []
  }
}
```

Travaux nécessaires :

- ajouter les entités actuellement absentes de `SyncController::pull` : comptes, produits, documents, supports digitaux, événements, tournées, notifications, stock, campagnes, opportunités, commandes, ventes, dépenses, congés, formations et conversations ;
- filtrer chaque entité par utilisateur, filiale, rôle et périmètre territorial ;
- respecter `last_sync` sur `updated_at` ;
- transmettre les suppressions soft-delete via `deleted` ;
- charger les relations nécessaires aux ressources JSON ;
- retourner une date UTC exploitable par le mobile ;
- couvrir ce contrat par un test API authentifié.

### `POST /api/v1/sync/push`

Le backend doit accepter les créations et modifications offline de :

- contacts ;
- établissements ;
- activités et comptes rendus ;
- tâches ;
- tournées ;
- dépenses ;
- congés ;
- mouvements d'échantillons.

Le traitement doit :

- rechercher une donnée existante par `local_uuid` avant `id` ;
- être idempotent afin d'éviter les doublons après reconnexion ;
- appliquer le `user_id` et `subsidiary_id` du compte authentifié côté serveur ;
- retourner le résultat élément par élément ;
- retourner les erreurs de validation par élément ;
- retourner le mapping `local_uuid -> server_id` ;
- retourner les conflits de version ou de validation ;
- journaliser l'opération et sa date de traitement.

Réponse attendue :

```json
{
  "status": "partial_success",
  "processed": {"activities": 2, "tasks": 1},
  "failed": [
    {"entity": "activity", "local_uuid": "...", "errors": {}}
  ],
  "mapping": {"local_uuid": "server_uuid"},
  "timestamp": "2026-09-16T12:00:00Z"
}
```

## 2. Contrat des activités et visites

### Types de visite

`StoreActivityRequest` accepte actuellement `visite`, `appel`, `email`, `reunion` et `conference`. Le besoin métier mobile comprend aussi :

- visite médicale ;
- commerciale ;
- pharmacie ;
- établissement ;
- distributeur ;
- formation ;
- présentation produit ;
- suivi client ;
- enquête.

Le backend doit choisir une solution stable :

1. étendre l'énumération `activity_type` et les règles de validation ; ou
2. utiliser la table `activity-types` comme référentiel et stocker un code/UUID.

Le choix doit être reflété dans la migration, le modèle, le FormRequest, la Resource, les seeders et la documentation API.

### Champs à persister

L'API des activités doit accepter et retourner :

- `subsidiary_id` ;
- `tour_plan_id` ;
- `contact_id` ;
- `account_id` ;
- type de visite ;
- date, heure de début et heure de fin ;
- durée ;
- GPS de début et de fin avec précision ;
- motif/objectifs ;
- produits présentés ;
- réaction ou intérêt par produit ;
- contenus et supports montrés ;
- échantillons distribués ;
- commentaires et objections ;
- résultat ;
- prochaine action ;
- prochaine visite ;
- signature ;
- pièces jointes ;
- statut métier et statut de validation ;
- `local_uuid`.

Les champs actuellement envoyés par le mobile mais non pris en charge par le contrat serveur doivent être soit persistés, soit explicitement retirés de la documentation mobile.

## 3. Compte rendu de visite

Créer ou compléter un contrat dédié :

- `POST /api/v1/activities/{activity}/report` ;
- `GET /api/v1/activities/{activity}/report` ;
- `PUT /api/v1/activities/{activity}/report`.

Le compte rendu doit être rattaché à l'activité existante. Il ne doit pas créer une nouvelle visite de suivi automatiquement.

Le backend doit retourner les statuts :

- `draft` ;
- `submitted` ;
- `validated` ;
- `rejected` ;
- `modified`.

Il doit également retourner le motif de rejet, l'auteur de la validation, la date de validation et l'historique des modifications.

## 4. Produits présentés et contenus e-detailing

Le payload produit d'une activité doit conserver :

```json
{
  "product_id": "uuid",
  "quantity": 0,
  "customer_interest": "faible|moyen|fort",
  "reaction": "interesse|neutre|objection",
  "remarques": "..."
}
```

Le backend doit aussi relier les contenus montrés à l'activité :

- documentation scientifique ;
- brochure ;
- présentation ;
- vidéo ;
- fiche produit ;
- visuel ;
- support digital.

À garantir :

- relation activité-produit ;
- relation activité-document/support ;
- ordre de présentation ;
- durée de consultation si disponible ;
- date et utilisateur de consultation ;
- retour uniforme dans `ActivityResource`.

## 5. Échantillons et stock

Les routes existantes sont `GET /api/v1/my-stock` et `POST /api/v1/sample-movements`.

Le backend doit garantir :

- `product_id` valide ;
- `movement_type` validé ;
- quantité strictement positive ;
- refus explicite si le stock disponible est insuffisant ;
- `activity_id` obligatoire lors d'une remise liée à une visite ;
- destinataire HCP ou établissement ;
- date/heure de remise ;
- numéro de lot ;
- date d'expiration ;
- signature ou preuve de remise ;
- `local_uuid` pour l'idempotence offline ;
- historique filtrable par produit, lot, HCP, établissement, activité et délégué ;
- stock personnel, stock global et stock disponible cohérents.

`GET /my-stock` doit toujours retourner une structure stable avec le produit, la quantité disponible, la quantité distribuée, le seuil d'alerte, le statut et les lots.

## 6. Référentiels nécessaires au mobile

Les référentiels suivants doivent être complets, seedés et accessibles avec des réponses JSON stables :

- `/config/contact-types` ;
- `/config/account-types` ;
- `/config/product-types` ;
- `/specialities` ;
- `/segmentations` ;
- `/potentials` ;
- `/influences` ;
- `/galenic-forms` ;
- `/activity-types` ;
- `/activity-results` ;
- `/event-formats` ;
- territoires, zones, régions et secteurs.

Chaque référentiel doit retourner au minimum : `id`, `code`, libellé, statut et ordre d'affichage lorsque nécessaire.

## 7. Resources JSON à compléter

### `ContactResource`

Retourner :

- `contact_type_id` et `contact_type` ;
- spécialité ;
- établissement complet ;
- téléphone et email ;
- GPS ;
- territoire, segmentation, potentiel et influence ;
- historique ou endpoint historique ;
- compteurs d'activités ;
- `local_uuid`, `created_at`, `updated_at`.

### `AccountResource`

Retourner :

- `account_type_id` et `account_type` ;
- filiale ;
- adresse complète ;
- GPS ;
- contacts responsables ;
- spécialités ;
- potentiel ;
- zone/territoire ;
- historique des activités ;
- `local_uuid`, `created_at`, `updated_at`.

### `ProductResource`

Retourner :

- nom et référence ;
- catégorie ;
- présentation/forme galénique ;
- dosage ;
- principe actif ;
- prix et devise ;
- documentation ;
- visuels ;
- supports digitaux ;
- stock et seuil si autorisé ;
- statut et filiale.

### `ActivityResource`

Retourner les coordonnées sous une forme stable, les produits, les supports, les échantillons, le compte rendu, les statuts de validation, la signature, les pièces jointes et l'historique d'audit.

### Documents, supports et événements

- fournir des URLs signées de téléchargement ou de streaming ;
- retourner le type MIME, la taille, la version et la date ;
- relier les documents aux produits et campagnes ;
- retourner format d'événement, invitations, présence et check-in ;
- distinguer conférence, formation, webinaire et événement général.

## 8. RBAC, filiale et territoire

Le backend doit appliquer le filtrage, et non le mobile :

- utilisateur connecté ;
- rôle délégué, superviseur ou manager ;
- pays et filiale ;
- région, territoire, zone et secteur ;
- comptes et contacts assignés ;
- produits et documents autorisés ;
- événements accessibles au profil.

Toute réponse hors périmètre doit être exclue ou retourner `403` selon le contrat choisi.

## 9. Workflows et validations

Les APIs doivent exposer un statut et une piste d'audit pour :

- demande et planification de visite ;
- planning/tournée ;
- compte rendu ;
- échantillons ;
- dépenses ;
- objectifs ;
- congés/absences ;
- création ou modification de contacts ;
- création ou modification d'établissements ;
- modification après validation.

Chaque validation doit retourner :

- statut serveur ;
- validateur ;
- date ;
- commentaire ;
- motif de rejet ;
- prochaine action éventuelle.

## 10. Notifications et messagerie

Garantir les endpoints et payloads suivants :

- liste paginée des notifications ;
- notification non lue/lue ;
- marquage individuel et global ;
- type et priorité ;
- date serveur ;
- lien vers l'entité concernée ;
- conversations paginées ;
- messages, auteur, date, statut de lecture et pièces jointes.

Les notifications doivent être générées côté backend lors des validations, rejets, changements de planning, stock critique et nouvelles tâches.

## 11. Fichiers, signatures et médias

Créer ou compléter les endpoints pour :

- pièces jointes de visite ;
- signature électronique ;
- documents produit ;
- vidéos et visuels e-detailing ;
- justificatifs de dépenses ;
- photos de reçus ;
- URLs temporaires ;
- suppression contrôlée et audit des fichiers.

Les réponses doivent contenir une URL serveur, un type MIME, une taille, une version et les droits d'accès. Aucun chemin local mobile ne doit être considéré comme une ressource backend.

## 12. Tests backend à ajouter

Ajouter des tests API authentifiés pour :

- login et `/me` ;
- visibilité RBAC par territoire ;
- contacts et établissements ;
- produits et référentiels ;
- création, check-in, check-out et compte rendu ;
- produits présentés et échantillons ;
- stock insuffisant ;
- événements, documents et supports ;
- dépenses, congés, objectifs et validations ;
- notifications et messagerie ;
- `sync/pull` avec `last_sync` ;
- `sync/push` idempotent avec `local_uuid` ;
- erreurs `401`, `403`, `404` et `422` ;
- réponses vides sans données inventées.

Commandes de contrôle :

```bash
cd skynity-backend/CRM-backend
php artisan route:list --path=api/v1
php artisan test
php artisan db:seed
```

## Critères de clôture backend

- toutes les entités nécessaires au mobile sont exposées par une API documentée ;
- les seeders créent des données cohérentes entre utilisateurs, filiales, contacts, établissements, produits et activités ;
- les Resources JSON correspondent aux modèles consommés par le mobile ;
- les créations et mises à jour mobiles passent les FormRequests ;
- les suppressions et synchronisations sont idempotentes ;
- les règles RBAC et territoriales sont appliquées côté serveur ;
- les workflows retournent leur état serveur ;
- les pièces jointes et URLs médias sont accessibles selon les permissions ;
- les tests API couvrent les contrats réels utilisés par Flutter.
