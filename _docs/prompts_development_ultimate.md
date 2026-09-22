# Spécifications Complètes et Prompts de Développement (Séparés par Technologie) - MEDCRM PRO (Édition Définitive)

Ce document représente le cahier des charges technique ultra-détaillé de l'application MEDCRM PRO. 
Il a été fusionné à partir de tous les guides existants (y compris le cas concret TOGO-PHARMA). 
Tous les prompts sont strictement séparés par technologie. Vous pouvez copier l'intégralité d'une partie et la donner à l'équipe ou à l'IA responsable de cette stack spécifique.
Ce document est conçu pour atteindre un très haut niveau de détail (architecture, JSON, composants, tests, et contexte métier).

---

# 🚀 PARTIE 1 : PROMPTS BACKEND (LARAVEL 11+)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Backend Laravel 11. L'application est un CRM pharmaceutique avec un organigramme strict (ex: Togo-Pharma avec DR, DC, Superviseurs et Délégués). La base de données (migrations) et les routes (`routes/api.php`) sous `/api/v1/` existent déjà. Ta mission est d'implémenter la logique métier complexe (Business Logic) à l'intérieur des Controllers existants, des FormRequests (pour la validation stricte) et des API Resources (pour le formatage de sortie). 
> Contraintes :
> - Utilise Eloquent massivement, optimise les requêtes (évite le N+1 via `with()`).
> - Utilise des transactions (`DB::transaction`) pour toutes les opérations impliquant plusieurs tables.
> - Suis les standards PSR-12 et commente la complexité algorithmique.
> - Retourne des erreurs au format JSON standardisé : `{ "message": "...", "errors": {...} }` avec les bons codes HTTP."

### Backend 1 : Authentification & Sécurité (`AuthController`)
**Prompt :**
> Implémente la logique d'authentification dans `AuthController`.
> **1. Méthode `login(LoginRequest $request)` :**
> - Valide `email` et `password`.
> - Vérifie via `Auth::attempt`. Si échec, retourne 401.
> - Si succès, génère un token Sanctum : `$token = $user->createToken('auth-token', ['*'])->plainTextToken`.
> - Si `otp_enabled` est vrai dans la DB, retourne un statut 202 au lieu de 200, et ne retourne pas le token complet (juste un token temporaire restreint à `verify-otp`).
> - **Exemple Réponse 200 :**
> ```json
> {
>   "message": "Connexion réussie",
>   "access_token": "1|abc123def456...",
>   "token_type": "Bearer",
>   "user": {
>     "id": "uuid-...",
>     "email": "jean.dupont@medcrm.com",
>     "first_name": "Jean",
>     "last_name": "Dupont",
>     "role": "supervisor",
>     "force_password_change": false
>   }
> }
> ```
> 
> **2. Méthode `me()` :**
> - Retourne `auth()->user()->load(['roles', 'organizationalUnit.parent', 'territory', 'sector'])`.
> 
> **3. Méthode `forceChangePassword(ChangePasswordRequest $request)` :**
> - Reçoit `current_password`, `new_password`, `new_password_confirmation`.
> - Vérifie le mot de passe actuel via `Hash::check`.
> - Met à jour le password, et passe le flag `force_password_change` à false.

### Backend 2 : Hiérarchie & Organisation (`OrganizationController`)
**Prompt :**
> L'architecture dépend fortement d'un arbre hiérarchique complexe (Régions > Territoires > Secteurs).
> **1. Modèle `OrganizationalUnit` :**
> - Assure-toi que les relations `parent()` (`belongsTo`) et `children()` (`hasMany`) sont définies.
> - Crée une méthode `descendants()` récursive si nécessaire.
> 
> **2. Méthode `OrganizationController@tree` :**
> - Récupère toutes les unités "racines" (`whereNull('parent_id')`) avec `with('children.children...')`.
> - Format de réponse via une `OrganizationTreeResource` :
> ```json
> {
>   "data": [
>     {
>       "id": "uuid-region-maritime",
>       "name": "Région Maritime",
>       "type": "region",
>       "manager_id": "uuid-user-kossi-amouzou",
>       "children": [
>         {
>           "id": "uuid-secteur-lome-est",
>           "name": "Secteur Lomé Est",
>           "type": "sector",
>           "manager_id": "uuid-user-sena-kpodar",
>           "children": []
>         }
>       ]
>     }
>   ]
> }
> ```

### Backend 3 : Statistiques & Dashboards (`AnalyticsController`)
**Prompt :**
> Implémente la logique d'agrégation de données de `AnalyticsController`. Cette partie doit être performante.
> **1. `regionalDashboard(Request $request)` :**
> - Récupère le DR connecté (ex: M. Kodjo ADJOVI). Identifie sa région via `organizational_unit_id`.
> - Fais une requête complexe pour calculer le CA : `Sale::whereIn('user_id', $usersInRegionIds)->sum('amount')`.
> - Calcule le taux d'atteinte : Somme des CA / Somme des cibles dans `objectifs`.
> - Retourne la performance par secteur enfant. Utilise `DB::raw('SUM(amount)')` et `groupBy('sector_id')`.
> 
> **2. `objectionsStats(Request $request)` (Marketing - ex: Mme Afi MENSAH) :**
> - Effectue une jointure entre `objections`, `activity_objections`, `activities`, `activity_product` et `products`.
> - Code d'exemple : 
> ```php
> return DB::table('objections')
>     ->join('activity_objections', 'objections.id', '=', 'activity_objections.objection_id')
>     ->join('activity_product', 'activity_objections.activity_id', '=', 'activity_product.activity_id')
>     ->join('products', 'activity_product.product_id', '=', 'products.id')
>     ->select('products.name as product', 'objections.name as objection', DB::raw('count(*) as total'))
>     ->groupBy('products.id', 'objections.id')
>     ->orderByDesc('total')
>     ->get();
> ```

### Backend 4 : Fiche Produit Stricte & Laboratoires (`ProductController`)
**Prompt :**
> **1. Règle Métier :** Un produit est rattaché à **un seul laboratoire fournisseur**.
> **2. Endpoint `ProductController@show` :**
> - Doit retourner la fiche identité complète (DCI, Nom Commercial, Forme galénique, Conditionnement, Classe thérapeutique, Indications, Posologie, Contre-indications, PGHT).
> - La ressource API doit exclure les valeurs nulles.

### Backend 5 : Objectifs et Ventes Grossistes (`ObjectifController` & `SaleController`)
**Prompt :**
> **1. Fixation des Objectifs par le DC (`ObjectifController@store`) :**
> - Valide le payload JSON pour les bulk inserts (Tableau de `user_id`, `type_objectif`, `valeur_cible`, `date_debut`, `date_fin`).
> - Utilise `DB::transaction()` et `Objectif::upsert` pour éviter les doublons par mois/user.
> 
> **2. Ventes Grossistes (`SaleController@storeWholesale`) :**
> - Les délégués saisissent les sorties grossistes par secteur (ex: SOCOPHAR, TOP PHARMA).
> - Payload :
> ```json
> {
>   "periode": "2026-01",
>   "grossiste_id": "gross_socophar",
>   "secteur_id": "sec_lome_est",
>   "ventes": [
>     { "product_id": "prod_amoxil", "quantite_boites": 1500, "pght_unitaire": 2500.0 }
>   ]
> }
> ```
> - Logique : Calcule le chiffre d'affaires (1500 * 2500) et insère dans la table `sales` de façon transactionnelle.

### Backend 6 : Prescripteurs (HCP) & Arbitrage (`ContactController`)
**Prompt :**
> **1. Hiérarchie des Établissements :**
> - Lors du `store`, le Délégué affecte 1 établissement principal (`main_account_id`) obligatoire et $N$ secondaires (relation pivot `contact_account`). Statut = `en_attente`.
> - Route `validateContact` pour que le superviseur valide.
> 
> **2. Arbitrage de Portefeuille (`ContactController@arbitrage`) :**
> - La capacité d'un délégué est limitée (ex: 100 max).
> - Route `POST /api/v1/contacts/arbitrage`
> - Payload : `{ "new_contact_id": "...", "removed_contact_id": "...", "action": "reallocate_to_regional_pool" }`.
> - Logique métier : Dans la table pivot `contact_user`, met un `removed_at` sur le `removed_contact_id` pour le délégué courant (afin de préserver l'historique de visite). Ajoute le `new_contact_id` au délégué.

### Backend 7 : Demandes des Délégués (`RequestController`)
**Prompt :**
> **1. Système d'escalade DM -> SUP -> DC :**
> - Modèle `DelegateRequest`. Statuts : `en_attente_superviseur`, `rejete_superviseur`, `transmis_directeur_commercial`, `valide_final`.
> - `POST /api/v1/requests` (Création) : `type_demande` (ex: `echantillons_supplementaires`), `objet`, `description`.
> - `POST /api/v1/requests/{id}/approveBySupervisor` : Change le statut vers `transmis_directeur_commercial`.

### Backend 8 : Visites, Comptes-Rendus et Duos (`ActivityReportController` & `CoachingController`)
**Prompt :**
> **1. Validation et CRV (`ActivityReportController@update`) :**
> - Cycle de vie : `brouillon` -> `soumis_definitif` -> `valide_superviseur` / `correction_requise`.
> - Si le payload contient des `samples`, insère dans `sample_movements` avec `num_lot` et `date_peremption`. Sauvegarde `signature_base64`.
> - Calcule et sauvegarde le PGHT total de la visite.
> 
> **2. Planification du Duo (`CoachingController@storeSession`) :**
> - Le superviseur (ex: Kossi) planifie un duo avec un délégué (ex: Sena).
> - Payload : `{ "delegate_id": "...", "date_duo": "...", "objectifs_prevus": ["Faire 5 visites", "Obtenir 2 engagements"] }`. Sauvegarde en statut `planifie`.
> 
> **3. Bilan du Duo (`CoachingController@updateSessionReport`) :**
> - Reçoit le comparatif : `{ "resultats_constates": ["4 visites faites", "2 engagements"], "taux_atteinte_pct": 82.0 }`.
> - Passe le statut à `cloture`.

### Backend 9 : Offline Sync & Tracking (Mobile) (`SyncController` & `ClmAnalyticController`)
**Prompt :**
> **1. `SyncController@push` (Moteur Massive-Sync) :**
> C'est le cœur de la synchronisation offline. Le payload est massif :
> ```json
> {
>   "transactions": [
>     { "ref": "local-id-1", "endpoint": "activities/uuid1/report", "method": "PUT", "payload": { "statut": "soumis_definitif", "notes": "...", "samples": [{"product_id": "...", "qty": 2}] } }
>   ]
> }
> ```
> - Logique : Boucle sur le tableau. À l'aide du conteneur IoC de Laravel, route la transaction vers le bon service ou contrôleur interne. Enveloppe le tout dans `DB::transaction`.
> 
> **2. `ClmAnalyticController@store` :**
> - Validation : `activity_id`, `digital_support_id`, `duration_seconds` (integer). Insère dans `clm_presentation_analytics`.

### Backend 10 : Pharmacovigilance (Urgence) (`PharmacovigilanceReportController`)
**Prompt :**
> **1. `PharmacovigilanceReportController@store` :**
> - Sécurité maximale. FormRequest strict.
> - Si `severity === 'grave'`, utilise les Queues Laravel : `SendPharmaAlertJob::dispatch($report)->onQueue('high');`. Ce Job envoie un mail aux admins sans bloquer le processus principal.

### Backend 11 : Exports Excel/PDF (`AnalyticsController@export`)
**Prompt :**
> Implémente la génération de fichiers à la volée.
> - `request('format') === 'excel'` -> `maatwebsite/excel`.
> - `request('format') === 'pdf'` -> `barryvdh/laravel-dompdf`.

---
---

# 💻 PARTIE 2 : PROMPTS FRONTEND WEB (NUXT 3 / VUE)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Frontend Nuxt 3, Vue 3 (Composition API, `<script setup>`) et Tailwind CSS. L'application est un CRM (MEDCRM PRO) pour ordinateur (Direction et Superviseurs). Utilise Pinia pour le state, et `ofetch` (ou le `$fetch` natif de Nuxt) pour les appels vers `/api/v1/`.
> Contraintes UI : Layout par défaut avec un `Sidebar` (menu vertical gauche, bg-gray-900) et un `Header` (bg-white, shadow-sm)."

### Web 1 : Authentification & Layout
**Prompt :**
> **1. Typages :** Crée `types/index.ts`. `interface User { id: string; email: string; role: string; }`.
> **2. Store Pinia (`stores/auth.ts`) :**
> ```typescript
> import { defineStore } from 'pinia'
> export const useAuthStore = defineStore('auth', {
>   state: () => ({ user: null as User | null, token: useCookie('auth_token').value }),
>   getters: { isAuthenticated: (state) => !!state.token },
>   actions: {
>     async login(credentials) {
>       const data = await $fetch('/api/v1/login', { method: 'POST', body: credentials })
>       this.token = data.access_token;
>       useCookie('auth_token').value = data.access_token;
>       this.user = data.user;
>     }
>   }
> })
> ```
> **3. Page Login (`pages/login.vue`) :** Split screen image/formulaire.

### Web 2 : Dashboard Régional (DR)
**Prompt :**
> Crée `pages/regional/dashboard.vue`.
> - **Grille de KPIs :** `<StatCard title="CA Global" :value="data.ca" icon="CurrencyDollarIcon" />`.
> - **Graphiques :** Intègre `vue-chartjs`. BarChart CA par secteur.
> - **Tableau (DataGrid) :** Composant `<TourPlansTable :plans="plans" />`.

### Web 3 : Tableau des Objectifs (Directeur Commercial)
**Prompt :**
> Crée `pages/commercial/objectives.vue`.
> Interface "Spreadsheet" (Édition en ligne).
> - Les cellules de données contiennent `<InlineEditInput v-model="sup.target_ca" />`. Au `focus`, bordure bleue (`focus:ring-2 focus:ring-blue-500`). En cas de modif, fond jaune (`bg-yellow-50`).
> - Footer flottant : Bouton "Sauvegarder (X modifications)" effectuant un POST bulk array.

### Web 4 : Pipeline des Opportunités (Drag & Drop)
**Prompt :**
> Crée `pages/commercial/pipeline.vue`.
> - Vue Kanban horizontale : `<div class="flex gap-4 overflow-x-auto p-4 h-[calc(100vh-100px)]">`.
> - Colonnes (Prospection, Négociation, Gagnée, Perdue).
> - Intègre `vuedraggable`. Sur `@end`, récupère l'ID et fait un `PATCH /api/v1/opportunities/${card.id}/stage`. Optimistic UI update.

### Web 5 : Bibliothèque CLM (Marketing)
**Prompt :**
> Crée `pages/marketing/clm.vue`.
> - Grille de cartes (`grid-cols-1 md:grid-cols-3 xl:grid-cols-4`).
> - Modale d'Upload avec composant `<Dropzone>` (`border-dashed`). Envoi FormData avec Axios `onUploadProgress` pour la barre de chargement.

### Web 6 : Centre de Validation (Boîte de Réception) & Arbitrage (Superviseur)
**Prompt :**
> Crée `pages/supervisor/validations.vue`.
> - Vue scindée (Split View `w-1/3` liste, `w-2/3` contenu).
> - **Validations CRV :** Affiche le statut actuel. Boutons "Approuver (Verrouiller)" ou "Demander Correction".
> - **Arbitrage Portefeuille :** Onglet dédié. Si un délégué a > 100 médecins, l'UI montre une jauge rouge. Liste les médecins triés par fréquence de visite croissante. Avertissement "L'ancien prescripteur n'est pas supprimé, son historique est conservé". Bouton de permutation.

### Web 7 : Calendrier des Duos & Coachings
**Prompt :**
> Crée `pages/supervisor/coaching.vue`.
> - Wrapper Vue 3 de FullCalendar (`@fullcalendar/vue3`).
> - Clic sur jour vide -> Modale Planification (choix Délégué, saisie des Objectifs Prévus).
> - Clic sur duo passé -> Modale Bilan. Affiche un tableau "Objectif Prévu vs Résultat Obtenu". Utilise `<input type="range" min="1" max="100">` pour le taux d'atteinte. Saisie de commentaires. 

### Web 8 : Suivi Ventes Grossistes & Escalade des Demandes
**Prompt :**
> **1. Ventes :** Crée `pages/supervisor/wholesale.vue`. Composant `LineChart` comparant les Objectifs Fixés par le DC vs Ventes Grossistes Réelles saisies par le terrain.
> **2. Escalades :** Crée `pages/supervisor/requests.vue`. Affiche un Kanban des demandes (ex: Demande de budget). Le Superviseur peut les glisser dans la colonne "Transmis DC" ou les rejeter (ouvre une modale de justification).

### Web 9 : Générateur de Rapports PDF/Excel
**Prompt :**
> Crée `pages/analytics/report-builder.vue`.
> - Filtres complexes. Boutons "Télécharger PDF".
> - Logique Blob JS :
> ```javascript
> const res = await $fetch(`/api/v1/analytics/export?format=pdf`, { responseType: 'blob' })
> const url = window.URL.createObjectURL(new Blob([res]))
> const link = document.createElement('a')
> link.href = url
> link.setAttribute('download', 'report.pdf')
> link.click()
> ```

---
---

# 📱 PARTIE 3 : PROMPTS FRONTEND MOBILE (FLUTTER / CAPACITOR VUE)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Mobile (Flutter ou Ionic/Vue). L'application MEDCRM PRO est destinée aux délégués médicaux (souvent hors réseau 4G dans les hôpitaux). La particularité absolue est l'architecture Offline-First. 
> Tu dois créer/adapter tous les écrans du dossier `lib/presentation/views/` (Flutter) selon les normes."

### Mobile 1 : Architecture Offline (SQLite & Queue de synchronisation)
**Prompt :**
> Modifie/Crée `SyncEngineService`. L'architecture suit 4 principes :
> 1. `CheckNet` : Écoute le réseau avec `connectivity_plus`.
> 2. `syncDown()` (PULL) : Récupère la master data au login. Insère dans `local_contacts`, `local_products`, `local_visits`.
> 3. `queueRequest()` (PUSH QUEUE) : À chaque action (création brouillon, ajout médecin), n'appelle PAS l'API. Insère une ligne dans la table `sync_queue` (endpoint, methode, payload).
> 4. `syncUp()` (FLUSH QUEUE) : Dès le retour réseau, dépile la `sync_queue` en l'envoyant par batch à `POST /api/v1/sync/push`. Si erreur 422, affiche une alerte, si 500 retente 3 fois.

### Mobile 2 : Agenda du Jour & Workflow de Visite (`visit_report_view.dart`)
**Prompt :**
> C'est le cœur de l'App. Refonds `visit_report_view.dart`.
> - Gère le double statut :
>   - **"Sauvegarder Brouillon" (Bouton Gris)** : Sauvegarde locale dans `local_visits` avec `statut = brouillon`. Pas de contrôle des champs requis.
>   - **"Soumettre Définitivement" (Bouton Vert)** : Si des échantillons sont ajoutés, EXIGE le numéro de lot, la date de péremption, et la signature tactile. Change le `statut = soumis_definitif`. Pousse la requête dans la `sync_queue`.

### Mobile 3 : Traçabilité des Échantillons & Pad de Signature
**Prompt :**
> Dans l'étape des échantillons :
> - Affiche un tableau d'ajout rapide (Dropdown Produit filtré sur les actifs).
> - Affiche le **PGHT total** des échantillons remis pour sensibilisation financière.
> - Champs obligatoires dynamiques : Numéro de lot, Date de péremption.
> - **Signature :** Intègre un `SignaturePad` tactile (package `signature` en Flutter). Enregistre les courbes (strokes) et convertis en `toDataURL` Base64 lors du clic sur "Confirmer". Associe cette string au JSON de la visite.

### Mobile 4 : Arbitrage de Portefeuille HCP (`hcp_portfolio_arbitrage_view.dart`)
**Prompt :**
> L'écran d'arbitrage (nouveau module) se déclenche quand le quota de N prescripteurs est dépassé (ex: 100/100).
> - Affiche la jauge saturée.
> - Liste les prescripteurs actuels triés par "Date de dernière visite croissante" (le moins visité en premier).
> - Mets en surbrillance rouge le prescripteur "Recommandé pour retrait".
> - Affiche le texte : "L'ancien prescripteur ne sera pas supprimé, son historique restera accessible. Il sera reversé au Pool régional."
> - Au clic sur "Permuter", crée un payload API d'arbitrage.

### Mobile 5 : Saisie des Ventes Grossistes (`wholesale_sales_entry_view.dart`)
**Prompt :**
> Crée l'écran de déclaration de ventes pour les délégués (nouveau module).
> - Sélecteurs en haut : Mois/Année, et Nom du Grossiste (ex: SOCOPHAR).
> - Grille type "Excel" : Liste tous les produits. Colonne d'input numérique "Nombre de boîtes vendues".
> - Calcule automatiquement le "Chiffre d'Affaires" en multipliant par le PGHT du produit local.
> - Bouton de soumission.

### Mobile 6 : Demandes d'Escalade Délégué (`delegate_requests_view.dart`)
**Prompt :**
> Crée l'écran des demandes délégués.
> - Formulaire : Type (Budget exceptionnel, Congé, Échantillons supp), Description.
> - Attachement (Optionnel) : Utilise `image_picker` / `file_picker`.
> - Historique des demandes avec code couleur : Jaune (`En_Attente_Superviseur`), Rouge (`Rejeté`), Bleu (`Transmis_DC`), Vert (`Validé_Final`).

### Mobile 7 : Répertoire HCP et Établissements Secondaires (`hcp_form_screen.dart`)
**Prompt :**
> Modifie le formulaire de création de médecin existant.
> - Contrainte absolue : 1 "Établissement Principal" (Sélecteur simple obligatoire).
> - Possibilité d'ajouter de 0 à N "Établissements Secondaires" (Sélecteur multiple).
> - Statut par défaut de l'objet créé : `en_attente` (en attendant la validation de `manager_validations_view.dart` du superviseur).

### Mobile 8 : Présentation CLM avec Timer Invisible (`clm_viewer_screen.dart`)
**Prompt :**
> Le lecteur CLM doit être plein écran (utiliser le plugin `system_chrome` en Flutter pour masquer la barre de statut et la barre de navigation système Android/iOS).
> - Le délégué swipe pour changer de slide (PDF ou Images).
> - Déclare un `DateTime startTime = DateTime.now()`. À chaque changement de page (`onPageChanged`), calcule le `difference.inSeconds` depuis `startTime`.
> - Enregistre `{ "slide": index, "duration": x }` dans une liste interne.
> - À la fermeture, pousse ce tableau analytique dans la `sync_queue` et restaure l'interface système standard.

### Mobile 9 : Alerte Urgence Pharmacovigilance (`pharmacovigilance_view.dart`)
**Prompt :**
> C'est le SEUL écran qui bypass la `sync_queue` passive.
> - Formulaire rouge "Alerte Sécurité". Champs stricts : Produit, Sexe, Gravité (Grave / Non grave), Description.
> - Si `Gravité = Grave` :
>   - Appelle `connectivity_plus`. S'il y a du réseau, fais l'appel API REST `POST /api/v1/pharmacovigilance-reports` de manière synchrone, bloque l'UI avec un spinner, et affiche un popup "Alerte de sécurité transmise aux autorités".
>   - S'il n'y a pas de réseau, ajoute l'alerte en haut de la file (priorité 0) pour qu'elle parte à la seconde où le réseau revient.

---
*(Fin des spécifications techniques ultimes 100% alignées sur le cas réel TOGO-PHARMA, l'audit Flutter et la logique Backend)*
