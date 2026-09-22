# Spécifications Complètes et Prompts de Développement (Séparés par Technologie) - MEDCRM PRO

Ce document représente le cahier des charges technique ultra-détaillé de l'application MEDCRM PRO.
Tous les prompts sont strictement séparés par technologie. Vous pouvez copier l'intégralité d'une partie et la donner à l'équipe ou à l'IA responsable de cette stack spécifique.
Ce document est conçu pour atteindre un très haut niveau de détail (architecture, JSON, composants, tests).

---

# 🚀 PARTIE 1 : PROMPTS BACKEND (LARAVEL 11+)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Backend Laravel 11. L'application est un CRM pharmaceutique. La base de données (migrations) et les routes (`routes/api.php`) sous `/api/v1/` existent déjà. Ta mission est d'implémenter la logique métier complexe (Business Logic) à l'intérieur des Controllers existants, des FormRequests (pour la validation stricte) et des API Resources (pour le formatage de sortie). 
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
>       "id": "uuid-region-nord",
>       "name": "Région Nord",
>       "type": "region",
>       "manager_id": "uuid-user-dr",
>       "children": [
>         {
>           "id": "uuid-secteur-lille",
>           "name": "Secteur Lille",
>           "type": "sector",
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
> - Récupère le DR connecté. Identifie sa région via `organizational_unit_id`.
> - Fais une requête complexe pour calculer le CA : `Sale::whereIn('user_id', $usersInRegionIds)->sum('amount')`.
> - Calcule le taux d'atteinte : Somme des CA / Somme des cibles dans `objectifs`.
> - Retourne la performance par secteur enfant. Utilise `DB::raw('SUM(amount)')` et `groupBy('sector_id')`.
> 
> **2. `objectionsStats(Request $request)` (Marketing) :**
> - Effectue une jointure entre `objections`, `activity_objections`, `activities`, `activity_product` et `products`.
> - Code : 
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

### Backend 4 : Objectifs et Ventes (`ObjectifController` & `SaleController`)
**Prompt :**
> **1. `ObjectifController@store(BulkObjectifRequest $request)` :**
> - Valide le payload JSON :
> ```json
> {
>   "objectives": [
>     { "user_id": "uuid-1", "type_objectif": "ca", "valeur_cible": 150000, "date_debut": "2026-09-01", "date_fin": "2026-09-30" },
>     { "user_id": "uuid-2", "type_objectif": "quantite", "valeur_cible": 500, "date_debut": "2026-09-01", "date_fin": "2026-09-30" }
>   ]
> }
> ```
> - Utilise `DB::transaction()` et `Objectif::upsert($request->objectives, ['user_id', 'type_objectif', 'date_debut'], ['valeur_cible'])` pour gérer les doublons.
> 
> **2. `SaleController@index` :**
> - Implémente le pattern Query Builder conditionnel :
> ```php
> $query = Sale::with(['product', 'user', 'region']);
> $query->when($request->region_id, fn($q, $id) => $q->where('region_id', $id));
> $query->when($request->start_date, fn($q, $date) => $q->whereDate('created_at', '>=', $date));
> return SaleResource::collection($query->paginate($request->input('per_page', 50)));
> ```

### Backend 5 : Visites, Comptes-Rendus et Duos (`ActivityReportController` & `CoachingController`)
**Prompt :**
> **1. `ActivityReportController@validateReport` :**
> - Route : `POST /api/v1/activities/{id}/report/validate`.
> - Policy : Vérifie via une Gate ou Policy (`can('validate', $activity)`) que le superviseur gère ce délégué.
> - Met à jour `is_locked = true`, `locked_at = now()`.
> 
> **2. `CoachingController@storeSession` :**
> - Validation : `delegate_id` requis (existe dans users), `scheduled_date` (date >= today), `motif` (string, max 255).
> - Crée une entrée dans `coaching_sessions` (statut 'planned').
> 
> **3. `CoachingController@updateSessionReport` :**
> - Validation : `score_presentation` (integer, 1-10), `feedback` (string).
> - Met à jour l'entrée et passe le statut à 'completed'. Déclenche l'événement `CoachingSessionCompleted($session)`.

### Backend 6 : Offline Sync & Tracking (Mobile) (`SyncController` & `ClmAnalyticController`)
**Prompt :**
> **1. `SyncController@push` :**
> C'est le cœur de la synchronisation offline. Le payload est massif :
> ```json
> {
>   "transactions": [
>     { "ref": "local-id-1", "endpoint": "activities/uuid1/report", "method": "PUT", "payload": { "statut": "realisee", "notes": "...", "samples": [{"product_id": "...", "qty": 2}] } }
>   ]
> }
> ```
> - Logique : Boucle sur le tableau. À l'aide du conteneur IoC de Laravel, route la transaction vers le bon service ou contrôleur interne. Enveloppe le tout dans `DB::transaction`. En cas d'erreur sur une transaction, sauvegarde l'erreur dans un tableau `$errors` pour la renvoyer au client, mais continue les autres (si tu choisis une stratégie "continue on error").
> 
> **2. `ClmAnalyticController@store` :**
> - Validation : `activity_id`, `digital_support_id`, `duration_seconds` (integer).
> - Insère dans `clm_presentation_analytics`.

### Backend 7 : Pharmacovigilance (`PharmacovigilanceReportController`)
**Prompt :**
> **1. `PharmacovigilanceReportController@store` :**
> - Sécurité maximale. FormRequest avec `product_id` (exists), `patient_age` (numeric), `patient_gender` (in:M,F,O), `severity` (in:grave,non_grave), `description` (string).
> - Si `severity === 'grave'`, utilise les Queues Laravel : `SendPharmaAlertJob::dispatch($report)->onQueue('high');`. Ce Job envoie un mail aux admins via `Mail::to(...)`.

### Backend 8 : Exports Excel/PDF (`AnalyticsController@export`)
**Prompt :**
> Implémente la génération de fichiers à la volée.
> - Si `request('format') === 'excel'`, utilise Laravel Excel (`maatwebsite/excel`). Crée une classe `SalesExport implements FromQuery, WithHeadings, WithMapping`.
> ```php
> return Excel::download(new SalesExport($filters), 'sales_report.xlsx');
> ```
> - Si `request('format') === 'pdf'`, utilise DOMPDF (`barryvdh/laravel-dompdf`).
> ```php
> $pdf = PDF::loadView('exports.pdf.sales', compact('data'));
> return $pdf->download('sales_report.pdf');
> ```

---
---

# 💻 PARTIE 2 : PROMPTS FRONTEND WEB (NUXT 3 / VUE)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Frontend Nuxt 3, Vue 3 (Composition API, `<script setup>`) et Tailwind CSS. L'application est un CRM (MEDCRM PRO) pour ordinateur (Direction et Superviseurs). Utilise Pinia pour le state, et `ofetch` (ou le `$fetch` natif de Nuxt) pour les appels vers `/api/v1/`.
> Contraintes UI :
> - Layout par défaut avec un `Sidebar` (menu vertical gauche, bg-gray-900) et un `Header` (bg-white, shadow-sm).
> - Les pages sont dans le dossier `pages/`. Les composants réutilisables dans `components/`.
> - Utilise des icônes (ex: Heroicons via `@heroicons/vue/outline`).
> - Utilise des typages TypeScript rigoureux (interfaces) pour chaque objet métier."

### Web 1 : Authentification & Layout
**Prompt :**
> **1. Typages :** Crée un fichier `types/index.ts`. Ajoute `interface User { id: string; email: string; role: string; ... }`.
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
> **3. Page Login (`pages/login.vue`) :**
> Layout full screen `h-screen grid grid-cols-2`. Partie gauche = image médicale. Partie droite = formulaire. Gère l'état `loading` sur le bouton de connexion (afficher un spinner SVG `animate-spin`).

### Web 2 : Dashboard Régional (DR)
**Prompt :**
> Crée `pages/regional/dashboard.vue`.
> - **Grille de KPIs :** `<div class="grid grid-cols-4 gap-4">`. Chaque KPI utilise un composant `<StatCard title="CA Global" :value="data.ca" icon="CurrencyDollarIcon" />`.
> - **Graphiques :** Intègre `vue-chartjs`. Importe `Bar` depuis `vue-chartjs` et enregistre les contrôleurs Chart.js nécessaires. Affiche le CA par secteur.
> - **Tableau (DataGrid) :** Composant `<TourPlansTable :plans="plans" />`. Construit un `<table>` natif stylisé avec Tailwind (`min-w-full divide-y divide-gray-200`). Lignes zébrées (`even:bg-gray-50`). Colonnes : Superviseur, Mois, Statut (avec badge de couleur via composant `<Badge color="yellow">En attente</Badge>`), Actions (Bouton "Examiner").

### Web 3 : Tableau des Objectifs (Directeur Commercial)
**Prompt :**
> Crée `pages/commercial/objectives.vue`.
> C'est une interface de type "Spreadsheet" (Édition en ligne).
> - Affiche un `<table>`. Itère via `v-for="sup in supervisors"` sur les lignes.
> - Les cellules de données CA et Quantité contiennent un composant `<InlineEditInput v-model="sup.target_ca" />`.
> - Composant `<InlineEditInput>` : Un input textuellement plat (`bg-transparent border-none`), mais au `focus`, affiche une bordure bleue (`focus:ring-2 focus:ring-blue-500`). Lorsqu'il détecte une modification (`watch`), change le fond de la cellule en jaune pâle (`bg-yellow-50`) pour signifier qu'il y a des modifications non sauvegardées.
> - Footer flottant : Si des modifications existent, affiche une barre fixe en bas de l'écran avec un bouton "Sauvegarder (X modifications)". Au clic, extrais les data modifiées et envoie un array JSON à `POST /api/v1/objectifs`.

### Web 4 : Pipeline des Opportunités (Drag & Drop)
**Prompt :**
> Crée `pages/commercial/pipeline.vue`.
> - Utilise un wrapper de grille horizontale : `<div class="flex gap-4 overflow-x-auto p-4 h-[calc(100vh-100px)]">`.
> - Chaque colonne du Kanban représente un statut (Prospection, Négociation, Gagnée, Perdue).
> - Intègre `vuedraggable` (compatible Vue 3). 
> - Modèle de données : `const columns = ref([{ id: 'prospect', title: 'Prospection', cards: [...] }, ...])`.
> - Événement `@end` du drag : Récupère la carte déplacée, trouve son nouveau statut basé sur sa nouvelle colonne, et appelle de façon asynchrone `PATCH /api/v1/opportunities/${card.id}/stage`. Gère les erreurs en annulant le drag visuellement.

### Web 5 : Bibliothèque CLM (Marketing)
**Prompt :**
> Crée `pages/marketing/clm.vue`.
> - Composant principal : Grille de cartes (`grid-cols-1 md:grid-cols-3 xl:grid-cols-4`).
> - Chaque `<ClmCard>` affiche un thumbnail (image de fond couverte `bg-cover`), le titre, un `<Badge>` pour la version.
> - Modale d'Upload : Au clic sur "Ajouter un support", ouvre une `<Modal>` avec `<Dialog>` de HeadlessUI.
> - Composant `<Dropzone>` interne : Zone délimitée par une bordure pointillée (`border-2 border-dashed border-gray-300 rounded-lg p-10 text-center hover:border-blue-500`). Gère l'événement `@drop.prevent` et `@dragover.prevent`. 
> - Une fois un fichier déposé (PDF/MP4), utilise `FormData` pour préparer la requête multipart. Utilise Axios pour l'upload si tu as besoin de la progression de l'upload (`onUploadProgress`), ou XMLHttpRequest pur. Affiche une barre de progression en %.

### Web 6 : Centre de Validation (Boîte de Réception)
**Prompt :**
> Crée `pages/supervisor/validations.vue`. Ce layout nécessite une attention particulière.
> - Vue scindée (Split View). Le conteneur principal `flex h-full`.
> - Panneau gauche (`w-1/3 border-r overflow-y-auto`) : Liste des éléments. `<ul class="divide-y">`. Chaque `<li class="p-4 cursor-pointer hover:bg-gray-50">` avec Titre, Sous-titre et date. Gère un état `activeItem = ref(null)` pour mettre en évidence la ligne sélectionnée (`bg-blue-50`).
> - Panneau droit (`w-2/3 p-6 overflow-y-auto`) : Contenu de `activeItem`. Si `activeItem` est nul, affiche un empty state avec illustration "Sélectionnez un élément à valider".
> - Panneau droit (Contenu) : Affiche la fiche détaillée. En bas (`sticky bottom-0 bg-white p-4 border-t`), deux boutons larges `w-full`. L'un Vert "Approuver", l'autre Rouge "Rejeter". 

### Web 7 : Calendrier des Duos & Coachings
**Prompt :**
> Crée `pages/supervisor/coaching.vue`.
> - Installe et utilise le wrapper Vue 3 de FullCalendar (`@fullcalendar/vue3`, `@fullcalendar/daygrid`, `@fullcalendar/interaction`).
> - Initialise `calendarOptions` avec `events: fetchEvents`, `dateClick: handleDateClick`, `eventClick: handleEventClick`.
> - Les événements doivent être colorés en fonction de leur statut (`blue` pour planifié, `green` pour complété).
> - Modale Planification (`handleDateClick`) : Affiche un formulaire simple.
> - Modale de Bilan (`handleEventClick`) : Si l'événement est passé, affiche des `<input type="range" min="1" max="10" />` stylisés avec CSS Tailwind (`accent-blue-600`) pour noter les compétences.

### Web 8 : Générateur de Rapports PDF/Excel
**Prompt :**
> Crée `pages/analytics/report-builder.vue`.
> - Interface très corporate. Colonne de gauche avec des `<select>` multiples, et un `<DatePicker>` (vue-datepicker).
> - Colonne de droite avec l'aperçu et les actions.
> - Boutons d'export : Au clic sur "Télécharger PDF", tu dois gérer la réception d'un fichier binaire.
> ```javascript
> const downloadFile = async (format) => {
>   const res = await $fetch(`/api/v1/analytics/export?format=${format}`, { responseType: 'blob' })
>   const url = window.URL.createObjectURL(new Blob([res]))
>   const link = document.createElement('a')
>   link.href = url
>   link.setAttribute('download', `report.${format === 'excel' ? 'xlsx' : 'pdf'}`)
>   document.body.appendChild(link)
>   link.click()
>   link.remove()
> }
> ```

---
---

# 📱 PARTIE 3 : PROMPTS FRONTEND MOBILE (FLUTTER / CAPACITOR VUE)

**Prompt de contexte global (À fournir en premier) :**
> "Tu es un Expert Mobile. L'application est destinée aux délégués médicaux (souvent hors réseau 4G dans les hôpitaux). La particularité absolue est l'architecture Offline-First. 
> Contraintes UI :
> - Design "Touch-Friendly" (Gros boutons de min 44x44px).
> - Navigation fluide sans lags.
> - Utilise des plugins Capacitor natifs (Camera, Network, Geolocation, Filesystem) si tu es sur Vue/Ionic, ou les packages équivalents si Flutter."

### Mobile 1 : Architecture Offline (Pinia / SQLite & Queue de synchronisation)
**Prompt :**
> C'est le cœur de l'application. Crée `services/SyncService.ts`.
> 1. Détection Réseau : Utilise `@capacitor/network`. Stocke `isOnline` dans un store global.
> 2. PULL : Créé une méthode `syncDown()`. Elle appelle `GET /api/v1/sync/pull`. Elle reçoit la master data et l'insère dans IndexedDB/SQLite (via `localforage` ou `@capacitor-community/sqlite`).
> 3. PUSH QUEUE : Crée une table/store `sync_queue`. Chaque fois que l'UI fait un "Save", au lieu d'appeler Axios, appelle `SyncService.queueRequest({ endpoint: '...', method: 'PUT', payload: {...} })`. Ce service insère la requête dans le cache local et génère un UUID local temporel.
> 4. PUSH RUNNER : Méthode `syncUp()`. Si `isOnline` est true et `sync_queue.length > 0`, dépile tout dans un payload massif `{ transactions: [...] }`, et appelle `POST /api/v1/sync/push`. En cas de 200 OK, vide la table locale `sync_queue`.

### Mobile 2 : Agenda du Jour (Accueil Mobile)
**Prompt :**
> Crée l'écran `Home.vue` (ou `home_screen.dart`).
> - Layout iOS/Android classique : Un `AppBar` (Header) personnalisé, et un `BottomNavigationBar`.
> - Dans le corps principal : Affiche la date du jour en très gros (`text-3xl font-bold`).
> - En dessous, utilise un composant `<Timeline>`. Chaque "Visite" est un `<Card>` avec une ligne temporelle verticale sur la gauche. 
> - La carte affiche le nom du prescripteur, sa spécialité (ex: Cardiologue), et un bouton plein format "Démarrer" vert. S'il a cliqué sur Démarrer, capture immédiatement la position GPS (`@capacitor/geolocation`) et sauvegarde-la localement, puis navigue vers la vue de visite.

### Mobile 3 : Présentation CLM (Plein Écran & Tracking Invisible)
**Prompt :**
> Crée l'écran `CLMViewer.vue`.
> - **Exigence Système :** Appelle les plugins natifs pour masquer la Status Bar (l'heure/batterie du téléphone) afin que la présentation occupe 100% de l'écran physique.
> - Composant visionneuse de PDF ou d'images. Utilise un `Swiper` (`swiper/vue`) pour balayer entre les slides. Affiche des petits points de pagination discrets en bas.
> - **Logique métier (Tracking) :** Dans le `<script setup>`, déclare `let startTime = Date.now()`, `let currentSlide = 0`. Au trigger de l'événement `@slideChange`, calcule le delta : `const timeSpent = (Date.now() - startTime) / 1000`. Pousse `timeSpent` dans un tableau de tracking local `[ { slide: currentSlide, duration: timeSpent } ]`. Réinitialise `startTime`.
> - Fermeture : Bouton "X" transparent en haut à droite. À la fermeture, pousse l'objet analytique complet dans la `sync_queue` et restaure la Status Bar native.

### Mobile 4 : Remise d'Échantillons & Signature Numérique
**Prompt :**
> Crée l'écran `SampleDistribution.vue`. Cet écran nécessite le support du stylet ou du doigt.
> - **Section Haute :** Un `<Select>` natif pour choisir le produit dans le stock local, un `<Input type="number">` pour la quantité. Un bouton "Ajouter" pour construire un tableau dynamique de produits laissés.
> - **Section Basse (Signature) :** Implémente un canvas HTML5. Applique un script comme `signature_pad` pour avoir une gestion propre de l'épaisseur du trait en fonction de la vitesse (pour faire une vraie signature manuscrite). Applique une bordure grise au conteneur.
> - Bouton "Effacer" pour reset le Canvas.
> - Bouton "Confirmer" : Convertit le canvas en base64 via `toDataURL('image/png')`. Associe l'image au JSON de l'étape et passe à l'écran de Compte Rendu.

### Mobile 5 : Saisie Rapide du Compte Rendu (Tags & Voix)
**Prompt :**
> Crée l'écran final de la visite `VisitReport.vue`. Le but est d'éviter le clavier virtuel tactile autant que possible.
> - **Section Objections :** Itère sur la liste des objections. Affiche des `<Chip>` (ou boutons arrondis). Ils agissent comme des cases à cocher. S'il est sélectionné, le fond devient bleu et le texte blanc (`bg-blue-600 text-white`).
> - **Section Intérêt :** Un composant 5 étoiles. Chaque étoile est cliquable. L'état se met à jour instantanément (les étoiles 1 à X se colorent en jaune).
> - **Section Notes Libres :** Un `<textarea>`. À côté, un gros bouton avec une icône de Microphone. Si on appuie, utilise l'API Web Speech native (`window.SpeechRecognition`) ou un plugin Capacitor pour la dictée vocale. Le texte reconnu remplit automatiquement le textarea.
> - **Bouton Final :** "Soumettre la Visite". Compile l'étape 4 (Échantillons) et l'étape 5 (CR) dans un seul grand payload. Pousse dans la `sync_queue`. Redirige vers la `Home.vue` avec un toast de félicitations.

### Mobile 6 : Alerte Pharmacovigilance (Procédure d'Urgence)
**Prompt :**
> Crée l'écran `PharmaAlert.vue`.
> - **UI :** Le thème visuel doit changer radicalement (Header Rouge, icônes d'alerte).
> - Formulaire obligatoire : Produit (sélecteur), Âge (Number), Sexe (Radio button M/F/O), Gravité (Segmented Control ou Toggle : Grave / Non Grave), Description (Textarea).
> - **Logique Critique :** Contrairement au fonctionnement normal de l'App (qui pousse silencieusement dans la `sync_queue`), ce formulaire a priorité absolue.
> Si l'appareil a du réseau (via `Network.getStatus()`), appelle *immédiatement et directement* l'API `POST /api/v1/pharmacovigilance-reports` avec Axios. Affiche un "Loading" bloquant. Si le serveur retourne 200, affiche une popup "Alerte de sécurité transmise aux autorités". Si pas de réseau, informe l'utilisateur que l'alerte est gardée et partira en priorité 1 au retour du réseau.

### Mobile 7 : Notes de Frais (Appareil Photo)
**Prompt :**
> Crée l'écran `AddExpense.vue`.
> - Formulaire de base : Montant, Type (Repas, Péage, Hôtel), Date.
> - **Bouton "Prendre en Photo le Reçu"** : Utilise le plugin `@capacitor/camera`. Ouvre la caméra native (`Camera.getPhoto({ resultType: CameraResultType.Base64, source: CameraSource.Camera })`).
> - Affiche la photo miniature sous le bouton une fois prise.
> - À la soumission, intègre la string Base64 lourde dans le payload. Sauvegarde en `sync_queue` et optimise l'image si possible pour limiter l'usage de données.
