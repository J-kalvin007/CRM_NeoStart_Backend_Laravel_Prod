# Cahier des Charges Backend - Spécifications des APIs & Référentiels CRM-SKYNITY

Ce document fournit à l'équipe **Backend Laravel** l'ensemble des spécifications techniques, schémas de base de données, seeders et endpoints API requis pour alimenter et synchroniser l'application mobile.

---

## 1. Référentiel Professionnels de Santé (HCP / Médecins)

### A. Les 11 Attributs à Exposer dans l'API (`/api/v1/contacts`)
- `nom` (string) : Nom de famille du praticien.
- `prenom` (string) : Prénom du praticien.
- `telephone` (string) : Numéro de téléphone direct.
- `email` (string) : Adresse email officielle.
- `adresse` (string) : Adresse physique d'exercice.
- `specialite` (string) : Spécialité principale du responsable.
- `etablissement` (string/relation) : Établissement rattaché au responsable.
- `territoire` (string/relation) : Hiérarchie géographique (*ex. Côte d'Ivoire → Abidjan → Secteur Cocody*). Utilisation pour les règles d'accès RBAC par périmètre.
- `segmentation` (string) : Catégorisation métier (*Segment A - Prescripteur clé (4 v/mois)*, *Segment B*, *Segment C*).
- `potentiel` (string/enum) : Projection de la valeur commerciale/médicale future (*fort, moyen, faible*).
- `influence` (string/enum) : Statut de Leader d'opinion / KOL (*KOL Régional, Chef de service CHU, Formateur*).
- `historique` (array/json) : Timeline 360° des interactions passées (*visites, remises échantillons, objections, participation aux événements*).

---

## 2. Référentiel Établissements de Santé

### A. Les 5 Types d'Établissements (`account_types`)
1. `Hôpitaux` (Code: `HOP`)
2. `Cliniques` (Code: `CLI`)
3. `Centres médicaux` (Code: `CEN`)
4. `Cabinets` (Code: `CAB`)
5. `Pharmacies` (Code: `PHA`)

### B. Les 10 Informations Établissement à Exposer (`/api/v1/accounts`)
- `nom` (string) : Raison sociale de l'établissement.
- `type` (string) : L'un des 5 types ci-dessus.
- `adresse` (string) : Adresse physique.
- `gps_lat` / `gps_lng` (decimal) : Coordonnées GPS pour le guidage.
- `contacts` (relation) : Liste des praticiens rattachés.
- `responsables` (string) : Nom du médecin ou pharmacien responsable.
- `specialites` (array/json) : Spécialités médicales représentées.
- `potentiel` (string) : Potentiel de l'établissement.
- `zone_territoire` (string) : Zone hiérarchique rattachée.
- `historique` (array/json) : Historique d'interaction et de livraisons.

---

## 3. Référentiel Produits & Supports Promotionnels GED

### A. Les 8 Attributs Produit (`/api/v1/products`)
- `nom` (string) : Nom commercial.
- `code_sku` (string) : Référence produit SKU.
- `categorie` (string) : Domaine thérapeutique (*Cardiologie, Pédiatrie, etc.*).
- `presentation_galenique` (string) : Forme galénique (*Comprimé, gélule, sirop, injectable, crème, suppositoire, poudre*).
- `dosage` (string) : Quantité de substance active (*50mg, 20mg, 100mg/5ml*).
- `prix` (decimal + devise) : Prix unitaire.
- `documentation` (json) : Fichiers scientifiques & réglementaires (*RCP officiel, Notice patient, Monographie, Étude Phase III, AMM*).
- `visuels` (json) : Supports médias e-detailing (*Photo packaging 3D, brochure PDF illustrée, vidéo MP4, infographie*).

---

## 4. Activités de Terrain & Visites

### A. Les 9 Types de Visites (`activity_type`)
- `visite_medicale` : Visite classique à un praticien.
- `commerciale` : Vente directe / négociation commande.
- `pharmacie` : Référencement & contrôle stock officine.
- `etablissement` : Visite institutionnelle hôpital/clinique.
- `distributeur` : Visite grossiste / partenaire logistique.
- `formation` : Formation terrain du praticien par le délégué.
- `presentation_produit` : Lancement d'un nouveau produit.
- `suivi_client` : Suivi relationnel & fidélisation.
- `enquete` : Collecte de données & étude de marché.

### B. Les 14 Champs à Stocker par Compte-Rendu (`/api/v1/activities`)
1. `date_activite` & `heure_debut` / `heure_fin`
2. `gps_debut_lat` & `gps_debut_lng` (Check-in GPS)
3. `contact_id` (Praticien visité)
4. `account_id` (Établissement rattaché)
5. `motif` (Objectif de la visite)
6. `presented_products` (Produits présentés & réactions: *Intéressé/Neutre/Objection*)
7. `shown_contents` (Documents & Visuels e-detailing présentés)
8. `samples_distributed` (Échantillons remis & quantités)
9. `notes` / `commentaires` (Notes libres du délégué)
10. `resultat` (Issue globale de la visite)
11. `prochaine_action` (Action de suivi)
12. `prochain_rdv` (Date de relance)
13. `signature_base64` (Signature tactile électronique)
14. `attachments` (Photos / pièces jointes)

---

## 5. Gestion des Échantillons & Stockage

- **Stock Global (`global_stock`)** : Stock disponible au niveau national / entreprise.
- **Stock Personnel (`delegue_stock`)** : Stock alloué à chaque délégué.
- **Table `sample_distributions`** :
  - `user_id` (Délégué)
  - `contact_id` (Destinataire praticien/officine)
  - `product_id` & `quantity` (Quantité remise)
  - `lot_number` (Scanné via code-barres)
  - `distributed_at` (Horodatage certifié conforme traçabilité pharma)

---

## 6. Événements Marketing & Formations (Section 13)

### A. Les 4 Formats d'Événements (`/api/v1/events`)
1. `Congrès & Symposiums` (Grandes manifestations médicales)
2. `Conférences` (Prises de parole d'experts / KOLs)
3. `Formations HCP (Externe)` (Sessions de formation organisées par le labo pour les médecins externes)
4. `Webinaires (En ligne)` (Sessions distanciel en visioconférence pour plusieurs pays)

---

## 7. Circuit de Validation : Délégué ➔ Superviseur

Endpoints et statuts de validation à mettre en place (`pending_supervisor`, `approved_supervisor`, `rejected_supervisor`) :

| Périmètre | Table / Endpoint | Statut initial Délégué | Action Superviseur |
|---|---|---|---|
| **Dépenses** | `/api/v1/expenses` | `soumis_superviseur` | Valider / Rejeter la note de frais |
| **Objectifs** | `/api/v1/team-objectives` | `propose_delegue` | Valider les objectifs terrain |
| **Création/Modif Contact** | `/api/v1/contact-approvals` | `en_attente_validation` | Valider la fiche médecin |
| **Planning Hebdo** | `/api/v1/weekly-plannings` | `submitted_friday` | Valider le planning le lundi |
| **Dotation Échantillons** | `/api/v1/sample-requests` | `demande_soumise` | Approuver l'allocation de stock |
