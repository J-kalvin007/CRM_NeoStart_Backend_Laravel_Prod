# 📊 Audit Complet : Frontend (Nuxt) & Backend (Laravel)

Ce document présente un audit de l'état des connexions entre les écrans du Frontend web (Nuxt) et les endpoints du Backend (Laravel API). 

---

## 1. 🖥️ Audit Frontend (Nuxt 3)
*Emplacement : `CRM-frontend/app/pages/`*

Voici l'analyse des répertoires de pages existants sur le frontend et leur état de connexion présumé avec le backend.

| Dossier / Écran | Fonctionnalité | Endpoints Backend Requis (Statut) | Connecté au Backend ? |
|---|---|---|---|
| `login.vue`, `verify-otp.vue` | Authentification | `POST /api/v1/login`, `POST /api/v1/verify-otp` | 🟢 Connecté / À vérifier |
| `agenda/` | Planning / Tournées | `GET /api/v1/tour-plans`, `GET /api/v1/activities` | 🟠 Partiel (Endpoint dispo) |
| `analytics/` | Dashboard & Stats | `GET /api/v1/analytics/team-performance`, etc. | 🟠 Partiel |
| `carte/` | Carte GPS (Secteurs) | `GET /api/v1/users/locations` | 🔴 À Connecter |
| `commandes/`, `ventes/` | Gestion CA / Commandes | `GET /api/v1/orders`, `GET /api/v1/sales` | 🔴 À Connecter |
| `conges/` | Demandes de congés | `GET /api/v1/leaves` (Manquant ?) | 🔴 À Connecter |
| `depenses/` | Notes de frais | `GET /api/v1/expenses`, `POST /api/v1/expenses/*/submit` | 🔴 À Connecter |
| `echantillons/` | Gestion des stocks | `GET /api/v1/stocks`, `POST /api/v1/sample-movements` | 🔴 À Connecter |
| `etablissements/` | Fiches comptes (Hôpitaux) | `GET /api/v1/accounts` | 🔴 À Connecter |
| `formations/` | e-Learning | `GET /api/v1/trainings` | 🔴 À Connecter |
| `kol/` | Gestion des leaders | `GET /api/v1/kols` | 🔴 À Connecter |
| `objectifs/` | Suivi primes & quotas | `GET /api/v1/me/objectifs-summary` | 🔴 À Connecter |
| `pharmacovigilance/` | Alertes PV | `POST /api/v1/pharmacovigilance-reports` | 🔴 À Connecter |
| `professionnels/` | Fiches Praticiens (HCP) | `GET /api/v1/contacts` | 🔴 À Connecter |
| `supports/` | Présentations CLM | `GET /api/v1/documents`, `POST /api/v1/activities/*/clm-analytics` | 🔴 À Connecter |
| `visites/` | Rapports de visite | `POST /api/v1/activities`, `PUT /api/v1/activities/*/report` | 🔴 À Connecter |
| `administration/` | Back-office Manager | `GET /api/v1/users`, `GET /api/v1/roles`, `GET /api/v1/teams` | 🔴 À Connecter |

> [!WARNING] 
> La grande majorité des dossiers Nuxt actuels ne contiennent probablement que des maquettes statiques (UI) ou des données mockées. Un effort majeur de raccordement (`useFetch` vers `/api/v1/...`) est nécessaire sur le frontend.

---

## 2. ⚙️ Audit Backend (Endpoints et Wireframes requis)
*Emplacement : `CRM-backend/routes/api.php`*

Le backend dispose de **263 routes actives**. Voici les principaux blocs fonctionnels et les wireframes/écrans frontend qu'ils nécessitent pour être exploités :

### A. Core CRM (Praticiens, Établissements, Visites)
- **Endpoints :** `GET|POST /api/v1/contacts`, `/accounts`, `/activities`, `/tour-plans`
- **Wireframes nécessaires sur le Frontend :**
  - Tableau de bord des médecins (Liste avec filtres de spécialité/secteur).
  - Fiche détail d'un praticien (Historique des visites, potentiel).
  - Écran de calendrier / Drag & Drop pour la planification des tournées (`tour-plans`).
  - Formulaire de création de compte-rendu de visite (avec sélection des produits présentés).

### B. Performances & Analytiques (Dashboard Manager)
- **Endpoints :** `GET /api/v1/analytics/team-performance`, `/supervisor/alerts`, `/marketing/objections-stats`
- **Wireframes nécessaires sur le Frontend :**
  - Dashboard Superviseur : Vue d'ensemble avec KPIs (Nombre de visites, CA généré, Taux d'atteinte).
  - Panneau d'alertes en temps réel (Retards, No-shows).
  - Dashboard Marketing : Graphiques en camembert sur les "Objections stats" et "e-Detailing summary".

### C. Gestion d'Équipe & Coaching
- **Endpoints :** `GET|POST /api/v1/coaching/sessions`, `/users`, `/teams`, `/users/locations`
- **Wireframes nécessaires sur le Frontend :**
  - Carte interactive (Leaflet / Google Maps) pour afficher la position en direct des délégués.
  - Écran de supervision d'un délégué : Liste de ses sessions de coaching.
  - Formulaire "Nouvelle session de Coaching" (Points forts, points faibles, plan d'action).

### D. Finances & Logistique (Frais & Échantillons)
- **Endpoints :** `GET|POST /api/v1/expenses`, `/expenses/{id}/validate`, `/sample-movements`
- **Wireframes nécessaires sur le Frontend :**
  - Tableau d'approbation des notes de frais pour le manager (Boutons : Valider / Rejeter).
  - Écran de suivi du stock personnel de la visiteuse médicale (Jauges de boîtes restantes).

### E. Référentiels (Admin Back-office)
- **Endpoints :** `GET|POST /api/v1/products`, `/specialities`, `/regions`, `/sectors`
- **Wireframes nécessaires sur le Frontend :**
  - Interface d'administration globale : Tableaux CRUD (Create, Read, Update, Delete) classiques pour gérer la base de données sans passer par phpMyAdmin.

---

## 🚀 Prochaines Étapes Recommandées
1. **Frontend Nuxt :** Remplacer systématiquement les données en dur par des appels `useFetch` vers l'API Laravel, en commençant par le module `visites` et `professionnels` (le coeur métier).
2. **Gestion de l'État :** S'assurer que le token Bearer (`auth.json` ou Pinia Store) est bien injecté dans les requêtes de `useFetch` via des intercepteurs.
