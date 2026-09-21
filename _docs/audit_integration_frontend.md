# 📋 MEDCRM PRO — AUDIT COMPLET FRONTEND / BACKEND & GUIDE D'INTÉGRATION NUXT
**Version :** 1.0 — Septembre 2026  
**Périmètre :** CRM-frontend (Nuxt 3) ↔ CRM-backend (Laravel 12 API)

---

## TABLE DES MATIÈRES
1. [État des Connexions Frontend → Backend](#1-état-des-connexions-frontend--backend)
2. [Audit des Stores Pinia (Endpoints consommés)](#2-audit-des-stores-pinia)
3. [Écrans existants & état de connexion](#3-écrans-existants--état-de-connexion)
4. [Endpoints Backend disponibles & wireframes requis](#4-endpoints-backend-disponibles--wireframes-requis)
5. [Bugs critiques identifiés](#5-bugs-critiques-identifiés)
6. [Système de Rôles & Permissions Dynamiques](#6-système-de-rôles--permissions-dynamiques)
7. [PROMPTS D'INTÉGRATION FRONTEND (Nuxt 3)](#7-prompts-dintégration-frontend-nuxt-3)
8. [Matrice de Priorisation des Tâches](#8-matrice-de-priorisation-des-tâches)

---

## 1. État des Connexions Frontend → Backend

### Légende
- 🟢 **CONNECTÉ** : Le store Pinia consomme bien l'endpoint backend réel
- 🟠 **PARTIEL** : Connecté mais incomplet (champs manquants, endpoint secondaire absent)
- 🔴 **NON CONNECTÉ** : L'écran est en place mais utilise des données statiques ou mockées
- ⚫ **MANQUANT** : L'écran n'existe pas encore côté frontend

### Vue d'ensemble

| Module | Écran Nuxt | Store Pinia | Endpoint Backend | Statut |
|--------|-----------|-------------|-----------------|--------|
| **Auth** | `login.vue` | `auth.ts` | `POST /v1/login` | 🟢 |
| **Auth** | `verify-otp.vue` | `auth.ts` | `POST /v1/verify-otp` | 🟢 |
| **Auth** | `forgot-password.vue` | `auth.ts` | `POST /v1/forgot-password` | 🟢 |
| **Auth** | `reset-password.vue` | `auth.ts` | `POST /v1/auth/change-password` | 🟢 |
| **Profil** | `profil/index.vue` | `auth.ts` | `GET /v1/me` | 🟢 |
| **Activités / Visites** | `visites/index.vue` | `activities.ts` | `GET /v1/activities` | 🟠 |
| **Activités** | `visites/nouveau.vue` | `activities.ts` | `POST /v1/activities` | 🟠 |
| **Professionnels** | `professionnels/index.vue` | `contacts.ts` | `GET /v1/contacts` | 🟠 |
| **Établissements** | `etablissements/index.vue` | `accounts.ts` | `GET /v1/accounts` | 🟠 |
| **Échantillons** | `echantillons/index.vue` | `samples.ts` | `GET /v1/my-stock`, `POST /v1/sample-movements` | 🟠 |
| **Objectifs** | `objectifs/index.vue` | `objectifs.ts` | `GET /v1/objectifs` | 🟠 |
| **Analytics** | `analytics/index.vue` | `objectifs.ts` | `GET /v1/analytics/team-performance` | 🟠 |
| **Dépenses** | `depenses/index.vue` | `expenses.ts` | `GET /v1/expenses` | 🟠 |
| **Commandes** | `commandes/index.vue` | `orders.ts` | `GET /v1/orders` | 🟠 |
| **Pharmacovigilance** | `pharmacovigilance/index.vue` | `pharmacovigilance.ts` | `POST /v1/pharmacovigilance-reports` | 🔴 |
| **Agenda / Tournées** | `agenda/index.vue` | `tourPlans.ts` | `GET /v1/tour-plans` | 🔴 |
| **Carte GPS** | `carte/index.vue` | ❌ Absent | `GET /v1/users/locations` | 🔴 |
| **Supports CLM** | `supports/index.vue` | `documents.ts` | `GET /v1/documents` | 🔴 |
| **Coaching** | ⚫ Absent | ❌ Absent | `GET /v1/coaching/sessions` | ⚫ |
| **Campagnes** | `campagnes/index.vue` | `campaigns.ts` | `GET /v1/campaigns` | 🟠 |
| **Formations** | `formations/index.vue` | `trainings.ts` | `GET /v1/trainings` | 🔴 |
| **Équipes** | `administration/index.vue` | `teams.ts` | `GET /v1/teams` | 🔴 |
| **Utilisateurs** | `administration/index.vue` | `users.ts` | `GET /v1/users` | 🔴 |
| **Rôles / Permissions** | ⚫ Absent | `roles.ts` | `GET /v1/roles`, `GET /v1/permissions` | 🔴 |
| **Alertes Superviseur** | ⚫ Absent | ❌ Absent | `GET /v1/supervisor/alerts` | ⚫ |
| **Sync Offline** | ⚫ Absent | ❌ Absent | `GET/POST /v1/sync/pull` / `/push` | ⚫ |
| **Objections** | ⚫ Absent | ❌ Absent | `GET /v1/objections` | ⚫ |
| **Objectifs Summary** | ⚫ Absent | ❌ Absent | `GET /v1/me/objectifs-summary` | ⚫ |
| **KOL** | `kol/index.vue` | ❌ Absent | `GET /v1/kols` | 🔴 |
| **Événements** | `evenements/index.vue` | `events.ts` | `GET /v1/events` | 🔴 |
| **Ventes** | `ventes/index.vue` | `sales.ts` | `GET /v1/sales` | 🔴 |

---

## 2. Audit des Stores Pinia

### 2.1 Endpoints RÉELLEMENT connectés (extraits des stores)

```
Auth Store (auth.ts)
  POST /v1/login                    ✅
  POST /v1/verify-otp               ✅
  POST /v1/auth/change-password     ✅
  POST /v1/auth/toggle-otp          ✅
  POST /v1/auth/invite              ✅
  GET  /v1/me                       ✅
  POST /v1/logout                   ✅

Activities Store (activities.ts)
  GET    /v1/activities             ✅
  GET    /v1/activities/:id         ✅
  POST   /v1/activities             ✅
  PUT    /v1/activities/:id         ✅
  DELETE /v1/activities/:id         ✅

Contacts Store (contacts.ts)
  GET    /v1/contacts               ✅
  POST   /v1/contacts               ✅
  PUT    /v1/contacts/:id           ✅
  DELETE /v1/contacts/:id           ✅

Accounts Store (accounts.ts)
  GET    /v1/accounts               ✅
  POST   /v1/accounts               ✅
  PUT    /v1/accounts/:id           ✅
  DELETE /v1/accounts/:id           ✅

Samples Store (samples.ts)
  GET    /v1/my-stock               ⚠️ ENDPOINT INEXISTANT BACKEND
  GET    /v1/sample-movements       ✅
  POST   /v1/sample-movements       ✅

Objectifs Store (objectifs.ts)
  GET    /v1/objectifs              ✅
  POST   /v1/objectifs              ✅
  PUT    /v1/objectifs/:id          ✅
  DELETE /v1/objectifs/:id          ✅
  GET    /v1/analytics/team-performance ✅

Expenses Store (expenses.ts)
  GET    /v1/expenses               ✅
  POST   /v1/expenses               ✅

Orders Store (orders.ts)
  GET    /v1/orders                 ✅
  POST   /v1/orders                 ✅

TourPlans Store (tourPlans.ts)
  GET    /v1/tour-plans             ✅
  POST   /v1/tour-plans             ✅
  POST   /v1/tour-plans/optimize    ✅

Pharmacovigilance Store (pharmacovigilance.ts)
  GET    /v1/pharmacovigilance-reports ✅
  POST   /v1/pharmacovigilance-reports ✅
```

### 2.2 Endpoints Backend EXISTANTS non consommés par le frontend

```
GET  /v1/supervisor/alerts              ❌ Pas de store ni d'écran
GET  /v1/users/locations                ❌ Pas de store (carte GPS)
GET  /v1/me/objectifs-summary           ❌ Pas appelé (écran partiel)
POST /v1/activities/:id/clm-analytics   ❌ Pas de store CLM analytics
GET  /v1/coaching/sessions              ❌ Pas de store Coaching
POST /v1/coaching/sessions              ❌ Pas de store Coaching
GET  /v1/objections                     ❌ Pas de store Objections
GET  /v1/marketing/objections-stats     ❌ Pas de store Marketing Stats
GET  /v1/sync/pull                      ❌ Pas de store Sync
POST /v1/sync/push                      ❌ Pas de store Sync
GET  /v1/kols                           ❌ Pas de store KOL
```

---

## 3. Écrans existants & état de connexion

### 3.1 Bugs identifiés dans les pages existantes

#### `visites/index.vue` — 🟠 PARTIEL
- **Problème** : Les KPI affichent une valeur hardcodée `/60` au lieu d'utiliser `objectifs-summary`.
- **Manque** : Pas de connexion au pad de signature numérique lors de la distribution d'échantillons.
- **Fix requis** : Connecter `GET /v1/me/objectifs-summary` pour les totaux.

#### `echantillons/index.vue` — 🟠 PARTIEL
- **Problème** : Le store appelle `/v1/my-stock` mais cet endpoint n'existe pas dans le backend.
- **Fix requis** : Adapter le store pour appeler `/v1/stocks` avec filtre sur l'utilisateur connecté.

#### `objectifs/index.vue` — 🟠 PARTIEL
- **Problème** : La page appelle `GET /v1/objectifs` (liste complète) mais n'appelle pas `GET /v1/me/objectifs-summary` pour le délégué connecté.
- **Fix requis** : Distinguer le contexte selon le rôle actif.

#### `stores/auth.ts` — 🔴 BUG CRITIQUE
- **Problème** : Les getters `isDelegue()`, `isManager()`, `isDirection()` retournent **toujours `true`**.
- **Impact** : Tous les utilisateurs voient tout le contenu. Le RBAC est complètement inactif.
- **Fix requis** : Implémenter une vérification réelle basée sur `activeRoleCode`.

### 3.2 Écrans à créer de zéro

| Écran | Route | Rôles concernés | Priorité |
|-------|-------|-----------------|----------|
| Dashboard Superviseur enrichi | `/analytics` | `MANAGER_EQUIPE` | 🔴 Urgent |
| Carte GPS Équipe | `/carte` | `MANAGER_EQUIPE`, `DIR_COMMERCIALE` | 🔴 Urgent |
| Coaching Délégué | `/coaching` | `MANAGER_EQUIPE` | 🟠 Haute |
| Référentiel Objections | `/objections` | `DELEGUE_MEDICAL`, `MARKETING` | 🟠 Haute |
| Alertes Superviseur | `/alertes` | `MANAGER_EQUIPE` | 🔴 Urgent |
| Fiche CLM Visionneuse | `/supports/[id]/viewer` | `DELEGUE_MEDICAL` | 🟠 Haute |
| Dashboard Marketing | `/analytics/marketing` | `MARKETING` | 🟠 Haute |
| Gestion Rôles & Permissions | `/administration/roles` | `ADMIN_GROUPE` | 🟠 Haute |
| Mon Résumé Objectifs | `/objectifs/summary` | `DELEGUE_MEDICAL` | 🟠 Haute |
| Fiche détail Praticien | `/professionnels/[id]` | Tous | 🔴 Urgent |

---

## 4. Endpoints Backend disponibles & wireframes requis

### Groupe A — Authentification & Profil
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/login` | POST | ✅ `login.vue` existant |
| `/v1/verify-otp` | POST | ✅ `verify-otp.vue` existant |
| `/v1/me` | GET | ✅ `profil/index.vue` existant |
| `/v1/auth/change-password` | POST | ✅ Existant |
| `/v1/auth/toggle-otp` | POST | ⚫ Section "Sécurité" dans le profil |
| `/v1/auth/invite` | POST | ⚫ Modal "Inviter un utilisateur" dans Administration |

### Groupe B — SFA Core (Visites, Contacts, Comptes)
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/activities` | GET, POST | ✅ `visites/index.vue` — À enrichir |
| `/v1/activities/:id` | GET, PUT, DELETE | ⚫ `visites/[id].vue` — Détail visite + CR |
| `/v1/activities/:id/report` | PATCH | ⚫ Formulaire de compte rendu intégré |
| `/v1/contacts` | GET, POST | ✅ `professionnels/index.vue` |
| `/v1/contacts/:id` | GET, PUT | ⚫ `professionnels/[id].vue` — Fiche praticien |
| `/v1/accounts` | GET, POST | ✅ `etablissements/index.vue` |
| `/v1/accounts/:id` | GET, PUT | ⚫ `etablissements/[id].vue` — Fiche établissement |

### Groupe C — Tournées & Planning
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/tour-plans` | GET, POST | ⚫ `agenda/index.vue` — Calendrier |
| `/v1/tour-plans/optimize` | POST | ⚫ Bouton "Optimiser l'itinéraire" |
| `/v1/tour-plans/:id` | GET, PUT | ⚫ Détail plan de tournée |

### Groupe D — Échantillons & Stock
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/stocks` | GET | ✅ `echantillons/index.vue` — Jauges de stock |
| `/v1/sample-movements` | POST | ✅ Modal "Distribuer des échantillons" + SignaturePad |
| `/v1/sample-movements` | GET | ✅ Historique des mouvements |

### Groupe E — Performances & Analytics
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/analytics/team-performance` | GET | ⚫ Dashboard Superviseur avec classement |
| `/v1/supervisor/alerts` | GET | ⚫ Panneau d'alertes temps réel |
| `/v1/users/locations` | GET | ⚫ Carte Leaflet avec pins GPS |
| `/v1/me/objectifs-summary` | GET | ⚫ Onglet "Mes Objectifs" pour le délégué |
| `/v1/marketing/objections-stats` | GET | ⚫ Camembert d'objections |

### Groupe F — Coaching & RH
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/coaching/sessions` | GET, POST | ⚫ Écran Coaching complet |
| `/v1/users` | GET, POST | ⚫ Liste collaborateurs Administration |
| `/v1/users/:id` | GET, PUT | ⚫ Fiche collaborateur |

### Groupe G — Pharmacovigilance
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/pharmacovigilance-reports` | POST | ⚫ Formulaire de signalement mobile |
| `/v1/pharmacovigilance-reports` | GET | ✅ `pharmacovigilance/index.vue` |

### Groupe H — Objections & CLM
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/objections` | GET | ⚫ Picker d'objections dans le CR de visite |
| `/v1/objections` | POST | ⚫ Formulaire de création (Admin Marketing) |
| `/v1/clm-analytics` | POST | ⚫ Tracking invisible dans la visionneuse CLM |

### Groupe I — Administration & Configuration
| Endpoint | Méthode | Wireframe Frontend Requis |
|----------|---------|--------------------------|
| `/v1/roles` | GET | ⚫ Liste des rôles |
| `/v1/permissions` | GET | ⚫ Matrice des permissions |
| `/v1/products` | GET, POST | ⚫ Catalogue produits |
| `/v1/sectors` | GET, POST | ⚫ Gestion des secteurs |
| `/v1/teams` | GET, POST | ⚫ Gestion des équipes |
| `/v1/settings` | GET | ⚫ Page de paramètres globaux |
| `/v1/organization` | GET | ⚫ Arborescence organisationnelle |

---

## 5. Bugs critiques identifiés

### BUG 1 — RBAC non fonctionnel (🔴 CRITIQUE)
**Fichier** : `stores/auth.ts` lignes 91-101

**Problème actuel :**
```typescript
// TOUJOURS TRUE — DANGEREUX
isDelegue(): boolean { return true; },
isManager(): boolean { return true; },
isDirection(): boolean { return true; },
```

**Impact** : Tous les utilisateurs voient toutes les fonctionnalités. Un délégué peut accéder à l'administration.

**Fix requis :**
```typescript
isDelegue(): boolean {
  return ['DELEGUE_MEDICAL'].includes(this.activeRoleCode ?? '');
},
isManager(): boolean {
  return ['MANAGER_EQUIPE', 'DIR_COMMERCIALE', 'DIR_GENERALE', 'ADMIN_GROUPE'].includes(this.activeRoleCode ?? '');
},
isDirection(): boolean {
  return ['DIR_COMMERCIALE', 'DIR_GENERALE', 'DIR_MEDICALE', 'MARKETING', 'ADMIN_GROUPE', 'ADMIN_PAYS'].includes(this.activeRoleCode ?? '');
},
isAdmin(): boolean {
  return ['ADMIN_GROUPE', 'ADMIN_PAYS'].includes(this.activeRoleCode ?? '');
},
```

### BUG 2 — Endpoint `/v1/my-stock` inexistant (🔴 CRITIQUE)
**Fichier** : `stores/samples.ts` ligne 76

**Problème** : `$api('/v1/my-stock')` → 404 Not Found  
**Fix** : Changer en `$api('/v1/stocks', { params: { user_id: auth.user?.id } })`

### BUG 3 — KPI de visites hardcodés (🟠 MAJEUR)
**Fichier** : `pages/visites/index.vue`  
**Problème** : `{{ totalVisitsThisMonth }} / 60` — le 60 est une constante magique  
**Fix** : Connecter `GET /v1/me/objectifs-summary` et remplacer par `target_visits`

### BUG 4 — Signature numérique non connectée (🟠 MAJEUR)
**Fichier** : `pages/echantillons/index.vue`  
**Problème** : La distribution d'échantillons ne collecte pas la signature numérique du médecin ni le `numero_lot`.  
**Fix** : Ajouter un composant `SignaturePad.vue` (canvas tactile)

### BUG 5 — Middleware auth sans protection par rôle (🟠 MAJEUR)
**Fichier** : `app/middleware/auth.global.ts`  
**Problème** : Le middleware vérifie seulement `isLogged`, pas le rôle. Un délégué peut accéder à `/administration`.  
**Fix** : Créer un middleware `role-guard.ts`

---

## 6. Système de Rôles & Permissions Dynamiques

### 6.1 Architecture cible (RBAC dynamique)

**Flux attendu :**
```
Login
  → GET /v1/me
  → Réponse : { roles: ['Délégué Médical'], permissions: ['visites.create', ...] }
  → authStore.activeRoleCode = 'DELEGUE_MEDICAL'
  → Middleware filtre les routes protégées
  → UI affiche/masque les sections selon les permissions
```

### 6.2 Correspondance Rôles Backend → Frontend

| Rôle Backend (Spatie) | Code Frontend | Scope de données |
|-----------------------|--------------|-----------------|
| `Administrateur Groupe` | `ADMIN_GROUPE` | GLOBAL |
| `Administrateur Pays` | `ADMIN_PAYS` | PAYS |
| `Direction Générale` | `DIR_GENERALE` | GLOBAL |
| `Direction Médicale` | `DIR_MEDICALE` | GLOBAL |
| `Direction Commerciale` | `DIR_COMMERCIALE` | GLOBAL |
| `Responsable Marketing` | `MARKETING` | PAYS |
| `Superviseur` | `MANAGER_EQUIPE` | EQUIPE |
| `Délégué Médical` | `DELEGUE_MEDICAL` | PERSONNEL |
| `Finance` | `FINANCE` | PAYS |
| `RH` | `RH` | PAYS |
| `Auditeur` | `AUDITEUR` | READ_ONLY_AUDIT |

### 6.3 Matrice d'accès par rôle

| Route | ADMIN | DIR_COM | MARKETING | MANAGER | DELEGUE |
|-------|-------|---------|-----------|---------|---------|
| `/` (Dashboard) | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/visites` | ✅ | ✅ | 👁️ | ✅ | ✅ |
| `/professionnels` | ✅ | ✅ | 👁️ | ✅ | ✅ |
| `/echantillons` | ✅ | ❌ | ❌ | 👁️ | ✅ |
| `/objectifs` | ✅ | ✅ | ❌ | ✅ | 👁️ |
| `/analytics` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `/carte` | ✅ | ✅ | ❌ | ✅ | ❌ |
| `/coaching` | ✅ | ❌ | ❌ | ✅ | ❌ |
| `/pharmacovigilance` | ✅ | ❌ | ❌ | 👁️ | ✅ |
| `/campagnes` | ✅ | ✅ | ✅ | 👁️ | ❌ |
| `/administration` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/depenses` | ✅ | ✅ | ❌ | ✅ | ✅ |

> 👁️ = Lecture seule | ✅ = Accès complet | ❌ = Accès refusé

---

## 7. PROMPTS D'INTÉGRATION FRONTEND (Nuxt 3)

---

### PROMPT FE-01 : Correction du RBAC — Getters & Middleware

**Contexte :** Le système de rôles ne fonctionne pas. Les getters `isDelegue`, `isManager`, `isDirection` retournent toujours `true`. Le middleware ne protège pas les routes par rôle.

#### 1. Corriger `stores/auth.ts` — Getters de rôle

```typescript
// stores/auth.ts — section getters
isDelegue(): boolean {
  const code = this.activeRoleCode;
  return !!code && ['DELEGUE_MEDICAL'].includes(code);
},
isManager(): boolean {
  const code = this.activeRoleCode;
  return !!code && ['MANAGER_EQUIPE', 'DIR_COMMERCIALE', 'DIR_GENERALE', 'ADMIN_GROUPE'].includes(code);
},
isDirection(): boolean {
  const code = this.activeRoleCode;
  return !!code && ['DIR_COMMERCIALE', 'DIR_GENERALE', 'DIR_MEDICALE', 'MARKETING', 'ADMIN_GROUPE', 'ADMIN_PAYS'].includes(code);
},
isAdmin(): boolean {
  const code = this.activeRoleCode;
  return !!code && ['ADMIN_GROUPE', 'ADMIN_PAYS'].includes(code);
},
hasPermission(): (permission: string) => boolean {
  return (permission: string) => {
    const perms = (this.user as any)?.permissions ?? [];
    return perms.includes(permission);
  };
},
```

#### 2. Créer `app/middleware/role-guard.ts`

```typescript
import { useAuthStore } from '~~/stores/auth';

const ROUTE_PERMISSIONS: Record<string, string[]> = {
  '/administration': ['ADMIN_GROUPE', 'ADMIN_PAYS'],
  '/analytics': ['ADMIN_GROUPE', 'DIR_COMMERCIALE', 'MARKETING', 'MANAGER_EQUIPE', 'DIR_GENERALE'],
  '/carte': ['ADMIN_GROUPE', 'MANAGER_EQUIPE', 'DIR_COMMERCIALE'],
  '/coaching': ['MANAGER_EQUIPE', 'ADMIN_GROUPE'],
  '/objectifs': ['ADMIN_GROUPE', 'DIR_COMMERCIALE', 'MANAGER_EQUIPE', 'DELEGUE_MEDICAL'],
  '/campagnes': ['MARKETING', 'ADMIN_GROUPE', 'DIR_COMMERCIALE'],
  '/pharmacovigilance': ['DELEGUE_MEDICAL', 'ADMIN_GROUPE', 'DIR_MEDICALE'],
  '/alertes': ['MANAGER_EQUIPE', 'ADMIN_GROUPE', 'DIR_COMMERCIALE'],
};

export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore();
  const role = auth.activeRoleCode;
  
  const requiredRoles = ROUTE_PERMISSIONS[to.path];
  
  if (requiredRoles && role && !requiredRoles.includes(role)) {
    return navigateTo('/');
  }
});
```

#### 3. Mettre à jour la navigation dans le layout

```vue
<!-- Dans app/layouts/default.vue ou la sidebar -->
<NuxtLink v-if="auth.isManager" to="/carte">Carte GPS Équipe</NuxtLink>
<NuxtLink v-if="auth.isAdmin" to="/administration">Administration</NuxtLink>
<NuxtLink v-if="auth.isDelegue" to="/echantillons">Mes Échantillons</NuxtLink>
<NuxtLink v-if="auth.isManager" to="/coaching">Coaching</NuxtLink>
<NuxtLink v-if="auth.isManager" to="/alertes">Alertes Équipe</NuxtLink>
```

---

### PROMPT FE-02 : Correction de l'Endpoint `/v1/my-stock`

**Contexte :** Le store `samples.ts` appelle `/v1/my-stock` qui n'existe pas en backend.

**Fix dans `stores/samples.ts` :**

```typescript
async fetchMyStock() {
  const { $api } = useNuxtApp();
  const auth = useAuthStore();
  this.loading = true;
  this.errorMessage = null;

  try {
    const res: any = await $api('/v1/stocks', { 
      method: 'GET',
      params: { user_id: auth.user?.id }
    });
    const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : [];
    this.stockSummary = list.map((item: any) => ({
      id: item.id || item.product_id,
      product_id: item.product_id || item.id,
      product: item.product?.nom || item.product_name || 'Produit',
      lot: item.numero_lot || '-',
      quantity: Number(item.quantite_disponible || item.quantite || 0),
      status: Number(item.quantite_disponible || 0) <= 5 ? 'critique' : 'normal',
    }));
    return this.stockSummary;
  } catch (error: any) {
    this.errorMessage = error?.message || "Erreur stock.";
    throw error;
  } finally {
    this.loading = false;
  }
},
```

---

### PROMPT FE-03 : Connexion de la page Agenda / Tournées

**Contexte :** La page `agenda/index.vue` doit afficher un calendrier interactif lié au plan de tournée du délégué.

**Tâches :**

1. Créer `stores/agenda.ts` :

```typescript
import { defineStore } from 'pinia';

export const useAgendaStore = defineStore('agenda', () => {
  const tourPlans = ref([]);
  const todayActivities = ref([]);
  const loading = ref(false);

  async function fetchTodayPlan() {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const today = new Date().toISOString().split('T')[0];
      const res: any = await $api(`/v1/tour-plans?date=${today}`, { method: 'GET' });
      tourPlans.value = Array.isArray(res?.data) ? res.data : [];
      const acts: any = await $api(`/v1/activities?date=${today}`, { method: 'GET' });
      todayActivities.value = Array.isArray(acts?.data) ? acts.data : [];
    } finally {
      loading.value = false;
    }
  }

  async function optimizeRoute(tourPlanId: string, lat: number, lng: number) {
    const { $api } = useNuxtApp();
    return await $api('/v1/tour-plans/optimize', {
      method: 'POST',
      body: { tour_plan_id: tourPlanId, current_lat: lat, current_lng: lng }
    });
  }

  return { tourPlans, todayActivities, loading, fetchTodayPlan, optimizeRoute };
});
```

2. Dans `pages/agenda/index.vue`, appeler `fetchTodayPlan()` dans `onMounted`.

3. Ajouter un bouton "Optimiser l'itinéraire" qui utilise `navigator.geolocation.getCurrentPosition`.

---

### PROMPT FE-04 : Création de la page Carte GPS Équipe

**Contexte :** La page `/carte` doit afficher une carte Leaflet avec la position GPS de chaque délégué. Réservée aux Managers et Direction.

**Tâches :**

1. Installer : `npm install leaflet @vue-leaflet/vue-leaflet`

2. Créer `stores/locations.ts` :

```typescript
import { defineStore } from 'pinia';

export const useLocationsStore = defineStore('locations', () => {
  const locations = ref([]);
  const loading = ref(false);

  async function fetchTeamLocations() {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const res: any = await $api('/v1/users/locations', { method: 'GET' });
      locations.value = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : [];
    } finally {
      loading.value = false;
    }
  }

  return { locations, loading, fetchTeamLocations };
});
```

3. Créer `pages/carte/index.vue` :

```vue
<template>
  <div class="h-[calc(100vh-64px)] flex flex-col">
    <div class="p-4 border-b flex items-center justify-between bg-white">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Carte GPS Équipe</h1>
        <p class="text-xs text-slate-400">Positions en temps réel — {{ store.locations.length }} délégués actifs</p>
      </div>
      <button @click="store.fetchTeamLocations()" class="px-4 py-2 bg-[#0066FF] text-white rounded-xl text-xs font-bold">
        🔄 Actualiser
      </button>
    </div>
    
    <div class="flex flex-1 overflow-hidden">
      <!-- Carte Leaflet -->
      <ClientOnly>
        <LMap class="flex-1" :zoom="10" :center="[6.1375, 1.2125]">
          <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
          <LMarker v-for="loc in store.locations" :key="loc.id" :lat-lng="[loc.lat, loc.lng]">
            <LPopup>
              <strong>{{ loc.name }}</strong><br/>
              Statut : <span :class="loc.status === 'En visite' ? 'text-green-600' : 'text-gray-500'">{{ loc.status }}</span><br/>
              Vu à : {{ new Date(loc.last_seen).toLocaleTimeString() }}
            </LPopup>
          </LMarker>
        </LMap>
      </ClientOnly>

      <!-- Panneau latéral -->
      <div class="w-72 bg-white border-l p-4 overflow-y-auto">
        <h3 class="font-bold text-sm text-slate-700 mb-3">Équipe Terrain</h3>
        <div v-if="store.loading" class="text-xs text-slate-400">Chargement...</div>
        <div
          v-for="loc in store.locations"
          :key="loc.id"
          class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 cursor-pointer mb-1"
        >
          <span
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :class="loc.status === 'En visite' ? 'bg-green-500' : 'bg-gray-300'"
          />
          <div class="min-w-0">
            <p class="text-xs font-bold text-slate-800 truncate">{{ loc.name }}</p>
            <p class="text-[10px] text-slate-500">{{ loc.status }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['role-guard'] });
const store = useLocationsStore();
onMounted(() => store.fetchTeamLocations());

// Auto-refresh toutes les 2 minutes
let interval: ReturnType<typeof setInterval>;
onMounted(() => { interval = setInterval(() => store.fetchTeamLocations(), 120000); });
onUnmounted(() => clearInterval(interval));
</script>
```

---

### PROMPT FE-05 : Création du module Coaching Superviseur

**Contexte :** Karim doit créer des sessions de coaching. Backend : `GET/POST /v1/coaching/sessions`.

**Tâches :**

1. Créer `stores/coaching.ts` :

```typescript
import { defineStore } from 'pinia';

export interface CoachingSession {
  id?: string;
  supervisor_id?: string;
  delegate_id: string;
  delegate_name?: string;
  strengths: string;
  weaknesses: string;
  action_plan: string;
  next_meeting_date: string;
  joint_visit_date?: string;
  joint_visit_activity_id?: string;
  status?: string;
}

export const useCoachingStore = defineStore('coaching', () => {
  const sessions = ref<CoachingSession[]>([]);
  const loading = ref(false);

  async function fetchSessions(delegateId?: string) {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const url = delegateId
        ? `/v1/coaching/sessions?delegate_id=${delegateId}`
        : '/v1/coaching/sessions';
      const res: any = await $api(url, { method: 'GET' });
      sessions.value = Array.isArray(res?.data) ? res.data : [];
    } finally {
      loading.value = false;
    }
  }

  async function createSession(payload: CoachingSession) {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const res: any = await $api('/v1/coaching/sessions', { method: 'POST', body: payload });
      const created = res?.data || res;
      if (created) sessions.value.unshift(created);
      return created;
    } finally {
      loading.value = false;
    }
  }

  return { sessions, loading, fetchSessions, createSession };
});
```

2. Créer `pages/coaching/index.vue` avec :
   - Liste des délégués de l'équipe
   - Pour chaque délégué : KPI (visites, no-shows, objectif %)
   - Bouton "Nouvelle session de coaching"
   - Modal avec formulaire (points forts, points faibles, plan d'action, date)

---

### PROMPT FE-06 : Page Objectifs Délégué (Summary)

**Contexte :** Sophie doit voir ses objectifs du mois et sa prime estimée via `GET /v1/me/objectifs-summary`.

**Tâches :**

1. Ajouter dans `stores/objectifs.ts` la méthode `fetchMySummary` :

```typescript
async function fetchMySummary() {
  loading.value = true;
  try {
    const { $api } = useNuxtApp();
    const res: any = await $api('/v1/me/objectifs-summary', { method: 'GET' });
    return res?.data || res;
  } finally {
    loading.value = false;
  }
}
```

2. Créer `pages/objectifs/summary.vue` :

```vue
<template>
  <div class="space-y-6 p-4" v-if="summary">
    <h1 class="text-2xl font-black text-slate-800">Mes Objectifs — {{ currentMonth }}</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Visites -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Visites Réalisées</p>
        <div class="flex items-end gap-2 mt-2">
          <span class="text-4xl font-black text-[#0066FF]">{{ summary.completed_visits }}</span>
          <span class="text-slate-400 text-lg">/ {{ summary.target_visits }}</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2 mt-3">
          <div
            class="h-2 rounded-full transition-all duration-700"
            :class="summary.visit_rate >= 80 ? 'bg-emerald-500' : 'bg-[#0066FF]'"
            :style="`width: ${Math.min(summary.visit_rate, 100)}%`"
          />
        </div>
        <p class="text-xs text-slate-500 mt-1 font-medium">{{ summary.visit_rate }}% de l'objectif</p>
      </div>

      <!-- Taux global -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Performance Globale</p>
        <p class="text-4xl font-black mt-2" :class="summary.overall_rate >= 80 ? 'text-emerald-600' : 'text-amber-500'">
          {{ summary.overall_rate }}%
        </p>
        <p class="text-xs text-slate-500 mt-1">Moyenne pondérée de tous les objectifs</p>
      </div>

      <!-- Prime -->
      <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-5 text-white shadow-md">
        <p class="text-xs font-bold text-emerald-100 uppercase tracking-wider">Prime Estimée</p>
        <p class="text-3xl font-black mt-2">{{ formatCurrency(summary.prime_estimee) }}</p>
        <p class="text-xs text-emerald-200 mt-1">Calculée sur la base de votre taux d'atteinte</p>
      </div>
    </div>

    <!-- Détail par objectif -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b">
        <h2 class="font-bold text-slate-700">Détail par objectif</h2>
      </div>
      <div v-for="obj in summary.objectifs" :key="obj.type" class="p-4 border-b last:border-0 flex items-center gap-4">
        <div class="flex-1">
          <p class="text-sm font-bold text-slate-700 capitalize">{{ obj.label }}</p>
          <p class="text-xs text-slate-400">{{ obj.current }} / {{ obj.target }} {{ obj.unit }}</p>
        </div>
        <div class="w-32">
          <div class="w-full bg-slate-100 rounded-full h-1.5">
            <div
              class="h-1.5 rounded-full"
              :class="obj.rate >= 100 ? 'bg-emerald-500' : obj.rate >= 70 ? 'bg-amber-400' : 'bg-red-400'"
              :style="`width: ${Math.min(obj.rate, 100)}%`"
            />
          </div>
        </div>
        <span class="text-sm font-black w-12 text-right" :class="obj.rate >= 100 ? 'text-emerald-600' : 'text-slate-700'">
          {{ obj.rate }}%
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const summary = ref(null);
const store = useObjectifsStore();
const currentMonth = new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const formatCurrency = (val: number) => new Intl.NumberFormat('fr-FR', {
  style: 'currency', currency: 'XOF', minimumFractionDigits: 0
}).format(val || 0);

onMounted(async () => {
  summary.value = await store.fetchMySummary();
});
</script>
```

---

### PROMPT FE-07 : Composant SignaturePad pour les Échantillons

**Contexte :** Lors de la distribution, le médecin signe sur l'écran tactile.

1. Créer `app/components/SignaturePad.vue` :

```vue
<template>
  <div class="space-y-3">
    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">
      Signature du Médecin
    </label>
    <canvas
      ref="canvasRef"
      class="w-full h-32 border-2 border-dashed border-slate-300 rounded-xl bg-white touch-none cursor-crosshair"
      @mousedown="startDrawing"
      @mousemove="draw"
      @mouseup="stopDrawing"
      @mouseleave="stopDrawing"
      @touchstart.prevent="startDrawing"
      @touchmove.prevent="draw"
      @touchend="stopDrawing"
    />
    <div class="flex items-center gap-2">
      <button
        @click="clear"
        type="button"
        class="px-3 py-1.5 text-xs rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium"
      >
        ✕ Effacer
      </button>
      <button
        @click="validate"
        type="button"
        :disabled="!hasSigned"
        class="px-4 py-1.5 text-xs rounded-lg bg-[#0066FF] text-white font-bold disabled:opacity-40"
      >
        ✓ Valider la signature
      </button>
      <span v-if="validated" class="text-xs text-emerald-600 font-medium">✅ Signature enregistrée</span>
    </div>
  </div>
</template>

<script setup lang="ts">
const emit = defineEmits<{ (e: 'signed', dataUrl: string): void }>();
const canvasRef = ref<HTMLCanvasElement | null>(null);
const hasSigned = ref(false);
const validated = ref(false);
let drawing = false;
let ctx: CanvasRenderingContext2D | null = null;

onMounted(() => {
  if (!canvasRef.value) return;
  ctx = canvasRef.value.getContext('2d');
  canvasRef.value.width = canvasRef.value.offsetWidth * window.devicePixelRatio;
  canvasRef.value.height = 128 * window.devicePixelRatio;
  canvasRef.value.style.width = '100%';
  canvasRef.value.style.height = '128px';
  if (ctx) {
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    ctx.strokeStyle = '#1e293b';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
  }
});

const getPos = (e: MouseEvent | TouchEvent) => {
  const rect = canvasRef.value!.getBoundingClientRect();
  const point = 'touches' in e ? e.touches[0] : e;
  return { x: point.clientX - rect.left, y: point.clientY - rect.top };
};

const startDrawing = (e: MouseEvent | TouchEvent) => {
  drawing = true;
  const { x, y } = getPos(e);
  ctx?.beginPath();
  ctx?.moveTo(x, y);
};

const draw = (e: MouseEvent | TouchEvent) => {
  if (!drawing || !ctx) return;
  const { x, y } = getPos(e);
  ctx.lineTo(x, y);
  ctx.stroke();
  hasSigned.value = true;
  validated.value = false;
};

const stopDrawing = () => { drawing = false; };

const clear = () => {
  if (!canvasRef.value || !ctx) return;
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
  hasSigned.value = false;
  validated.value = false;
};

const validate = () => {
  if (!canvasRef.value) return;
  const dataUrl = canvasRef.value.toDataURL('image/png');
  emit('signed', dataUrl);
  validated.value = true;
};
</script>
```

2. Intégrer dans le modal de distribution d'échantillons dans `pages/echantillons/index.vue`.
3. Mettre à jour le payload `POST /v1/sample-movements` pour inclure `signature_data_url` et `numero_lot`.

---

### PROMPT FE-08 : Module Alertes Superviseur

**Contexte :** Karim reçoit des alertes opérationnelles via `GET /v1/supervisor/alerts`.

1. Créer `stores/alerts.ts` :

```typescript
export interface SupervisorAlert {
  type: 'no_show' | 'late' | 'objective_risk' | 'pharmacovigilance';
  delegate_name: string;
  delegate_id: string;
  message: string;
  count?: number;
  severity: 'critical' | 'warning' | 'info';
  created_at: string;
}

export const useAlertsStore = defineStore('alerts', () => {
  const alerts = ref<SupervisorAlert[]>([]);
  const loading = ref(false);

  async function fetchAlerts() {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const res: any = await $api('/v1/supervisor/alerts', { method: 'GET' });
      alerts.value = Array.isArray(res?.data) ? res.data : [];
    } finally {
      loading.value = false;
    }
  }

  const criticalAlerts = computed(() => alerts.value.filter(a => a.severity === 'critical'));
  const warningAlerts = computed(() => alerts.value.filter(a => a.severity === 'warning'));
  const alertCount = computed(() => alerts.value.length);

  return { alerts, criticalAlerts, warningAlerts, alertCount, loading, fetchAlerts };
});
```

2. Créer `pages/alertes/index.vue` avec :
   - Tableau des alertes filtrable par type/sévérité
   - Badges colorés (rouge = critique, orange = avertissement, bleu = info)
   - Bouton "Contacter" pour chaque délégué

3. Ajouter un badge dans la navbar affichant `alertCount` des alertes critiques.

---

### PROMPT FE-09 : Référentiel des Objections dans le CR de Visite

**Contexte :** Dans le formulaire CR, Sophie sélectionne une objection validée et voit l'argument suggéré.

1. Créer `stores/objections.ts` :

```typescript
export const useObjectionsStore = defineStore('objections', () => {
  const objections = ref([]);
  const loading = ref(false);

  async function fetchByProduct(productId: string) {
    const { $api } = useNuxtApp();
    loading.value = true;
    try {
      const res: any = await $api(`/v1/objections?product_id=${productId}`, { method: 'GET' });
      objections.value = Array.isArray(res?.data) ? res.data : [];
    } finally {
      loading.value = false;
    }
  }

  async function recordObjection(activityId: string, objectionId: string, wasConvinced: boolean) {
    const { $api } = useNuxtApp();
    return $api('/v1/activity-objections', {
      method: 'POST',
      body: { activity_id: activityId, objection_id: objectionId, was_convinced: wasConvinced }
    });
  }

  return { objections, loading, fetchByProduct, recordObjection };
});
```

2. Créer `app/components/ObjectionPicker.vue` :
   - Dropdown de sélection de l'objection
   - Affichage automatique de `recommended_argument` en bloc bleu
   - Toggle "Le médecin a-t-il été convaincu ?" (Oui / Non)

3. Intégrer `<ObjectionPicker>` dans `pages/visites/nouveau.vue`.

---

### PROMPT FE-10 : Dashboard Marketing Analytics

**Contexte :** Julie a besoin d'un dashboard avec les stats CLM et les objections.

1. Installer : `npm install chart.js vue-chartjs`

2. Créer `app/components/charts/BarChart.vue` et `PieChart.vue`.

3. Créer `pages/analytics/marketing.vue` :
   - Top supports CLM (données de `GET /v1/analytics/clm-usage`)
   - Répartition des objections (données de `GET /v1/marketing/objections-stats`)
   - Taux de conviction global, nombre de signalements PV
   - Filtres par période et par produit

---

### PROMPT FE-11 : Fiche Détail Praticien avec Historique

**Contexte :** Sophie consulte la fiche du Dr. Martin avant d'entrer dans le cabinet.

1. Créer `pages/professionnels/[id].vue` avec :
   - Header : Nom, Spécialité, Adresse, Catégorie (badge A/B/C), Potentiel (étoiles)
   - Onglet "Historique" : Timeline des visites avec statut et produits
   - Onglet "Prescriptions" : Produits prescrits + fréquence
   - Onglet "Notes" : Zone de saisie libre → sauvegardée dans `contacts.meta`
   - Bouton "Planifier une visite" → pré-remplit le formulaire visite

2. Enrichir `stores/contacts.ts` :

```typescript
async fetchContactWithHistory(id: string) {
  const { $api } = useNuxtApp();
  const [contactRes, activitiesRes] = await Promise.all([
    $api(`/v1/contacts/${id}`, { method: 'GET' }),
    $api(`/v1/activities?contact_id=${id}&per_page=20`, { method: 'GET' }),
  ]);
  return {
    ...(contactRes?.data || contactRes),
    activities: Array.isArray(activitiesRes?.data) ? activitiesRes.data : [],
  };
}
```

---

### PROMPT FE-12 : Page Administration — Gestion des Rôles & Permissions

**Contexte :** L'administrateur gère les permissions de chaque rôle depuis l'interface.

1. Enrichir `stores/roles.ts` :

```typescript
async function fetchRolesWithPermissions() {
  const { $api } = useNuxtApp();
  loading.value = true;
  try {
    const [rolesRes, permsRes] = await Promise.all([
      $api('/v1/roles', { method: 'GET' }),
      $api('/v1/permissions', { method: 'GET' }),
    ]);
    roles.value = Array.isArray(rolesRes?.data) ? rolesRes.data : [];
    permissions.value = Array.isArray(permsRes?.data) ? permsRes.data : [];
  } finally {
    loading.value = false;
  }
}

async function updateRolePermissions(roleId: string, permissionIds: string[]) {
  const { $api } = useNuxtApp();
  return $api(`/v1/roles/${roleId}/permissions`, {
    method: 'PUT',
    body: { permission_ids: permissionIds }
  });
}
```

2. Créer `pages/administration/roles.vue` :
   - Tableau croisé : Permissions en lignes (groupées par module), Rôles en colonnes
   - Checkboxes pour activer/désactiver chaque permission par rôle
   - Bouton "Sauvegarder les modifications"
   - Badge sur chaque module indiquant le nombre de permissions actives

---

## 8. Matrice de Priorisation des Tâches

| Priorité | Prompt | Tâche | Effort | Impact |
|----------|--------|-------|--------|--------|
| 🔴 P1 | FE-01 | Correction RBAC (getters + middleware) | 2h | CRITIQUE |
| 🔴 P1 | FE-02 | Fix endpoint `/v1/my-stock` | 30min | CRITIQUE |
| 🔴 P1 | FE-03 | Connexion Agenda & Tournées | 3h | CRITIQUE |
| 🔴 P1 | FE-11 | Fiche détail Praticien | 4h | CRITIQUE |
| 🟠 P2 | FE-04 | Carte GPS Équipe (Leaflet) | 4h | HAUTE |
| 🟠 P2 | FE-05 | Module Coaching Superviseur | 5h | HAUTE |
| 🟠 P2 | FE-06 | Page Objectifs Summary Délégué | 2h | HAUTE |
| 🟠 P2 | FE-07 | SignaturePad Échantillons | 3h | HAUTE |
| 🟡 P3 | FE-08 | Alertes Superviseur | 3h | MOYENNE |
| 🟡 P3 | FE-09 | Référentiel Objections dans CR | 3h | MOYENNE |
| 🟡 P3 | FE-10 | Dashboard Marketing Analytics | 5h | MOYENNE |
| 🔵 P4 | FE-12 | Administration Rôles & Permissions | 6h | PLANIFIÉE |

**Estimation totale** : ~43h de développement frontend

---

## ANNEXE — Architecture des Rôles selon le Contexte Métier Togo

### Hiérarchie organisationnelle
```
Direction Générale / Direction Commerciale (Filiale / National)
  └── Superviseurs (Région — couvrent plusieurs secteurs)
        └── Délégués Médicaux (1 secteur principal + secteurs secondaires temporaires)
```

### Règles métier à implémenter côté frontend

1. **Scope de données** : Lire le champ `scopes` retourné par `GET /v1/me` pour filtrer les données affichées.
2. **Affichage du périmètre actif** : Afficher en header le périmètre de l'utilisateur (ex: badge "Secteur : Lomé Centre").
3. **Délégué multi-secteurs** : Si `scopes.length > 1`, afficher un sélecteur de secteur actif.
4. **Filtrage automatique** : Transmettre le `scope_id` actif comme paramètre dans toutes les requêtes de liste.

```typescript
// Exemple dans un store
async fetchContacts() {
  const auth = useAuthStore();
  const scope = auth.user?.scopes?.[0]; // Secteur actif
  
  const params = scope ? { scope_type: scope.type, scope_id: scope.id } : {};
  const res = await $api('/v1/contacts', { method: 'GET', params });
  // ...
}
```
