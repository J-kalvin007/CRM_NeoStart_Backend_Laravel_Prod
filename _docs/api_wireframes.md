# Architecture Backend et Wireframes Web Détaillés - MEDCRM PRO

Ce document détaille de manière exhaustive les endpoints nécessaires pour le backend du CRM ainsi qu'une liste complète et détaillée des écrans (wireframes) requis pour l'application Web, allant des tableaux de bord jusqu'aux modales et pages de paramètres.

## 1. Authentification, Utilisateurs & Paramètres

### Endpoints Backend
- `POST /api/auth/login` : Authentification, retourne token et rôle.
- `POST /api/auth/forgot-password` : Réinitialisation de mot de passe.
- `POST /api/auth/reset-password` : Validation du nouveau mot de passe.
- `GET /api/users/me` : Profil de l'utilisateur connecté.
- `PUT /api/users/me` : Mise à jour du profil.
- `PUT /api/users/me/password` : Changement de mot de passe.
- `GET /api/users/settings` : Préférences utilisateur (thème, notifications).

### Écrans Web (Wireframes)
- **Écran de Connexion (Login)** : Formulaire email/mot de passe avec option "Se souvenir de moi".
- **Écran "Mot de passe oublié"** : Saisie de l'email pour récupération.
- **Écran "Réinitialisation de mot de passe"** : Saisie du nouveau mot de passe.
- **Header/Menu Global** : Topbar avec profil utilisateur, sélecteur de région/secteur (si applicable), barre de recherche globale, cloche de notifications avec compteur, bouton déconnexion. Menu latéral (Sidebar) rétractable avec navigation par rôles.
- **Page Profil Utilisateur** : Informations personnelles, historique de connexion, statistiques personnelles.
- **Page Paramètres Utilisateur** : Changement de mot de passe, préférences de notifications (email/push), préférences d'affichage (mode sombre/clair).

---

## 2. Espace Directeur Marketing (Julie)

### Endpoints Backend
- `GET /api/marketing/dashboard` : Données du tableau de bord.
- `GET /api/clm/materials` : Liste des supports CLM.
- `POST /api/clm/materials` : Créer un support CLM.
- `GET /api/clm/materials/{id}` : Détails d'un support.
- `PUT /api/clm/materials/{id}` : Modifier/Archiver/Valider un support.
- `GET /api/campaigns` : Liste des campagnes.
- `POST /api/campaigns` : Créer une campagne marketing.
- `GET /api/campaigns/{id}` : Détails et métriques d'une campagne.
- `PUT /api/campaigns/{id}` : Édition de campagne.
- `GET /api/objections/analysis` : Statistiques sur les objections.
- `GET /api/segments` : Liste des segments médecins.
- `POST /api/segments` : Création de segment ciblé.
- `GET /api/segments/{id}` : Détails d'un segment et liste des médecins correspondants.
- `GET /api/marketing/reports/export` : Export PDF/Excel.

### Écrans Web (Wireframes)
- **Dashboard Marketing Principal** : KPIs globaux (adoption CLM globale, taux de couverture des campagnes), Top 5 produits, Top 5 objections, alertes marketing.
- **Liste des Supports CLM** : Tableau paginé avec filtres (par produit, par statut, date, auteur). Miniatures de prévisualisation.
- **Vue Détail Support CLM** : Statistiques d'usage (temps moyen de présentation, slides les plus vues), historique des versions, liste des campagnes liées.
- **Modale de Création/Édition de Support CLM** : Formulaire (Nom, Produit lié, Version, Auteur), zone de drag & drop pour l'upload (PDF/Vidéo/Zip), définition du public cible.
- **Liste des Campagnes** : Tableau de bord des campagnes actives, en attente, et terminées. Jauges d'objectif de couverture.
- **Vue Détail Campagne** : Avancement en temps réel, liste des délégués performants sur la campagne, statistiques de conversion.
- **Créateur de Campagne (Wizard)** : Formulaire en plusieurs étapes : 1. Infos de base, 2. Ciblage (Sélecteur de segment), 3. Association du CLM, 4. Objectifs.
- **Tableau de Bord des Objections** : Graphiques d'évolution des objections dans le temps, top objections par spécialité et par produit.
- **Constructeur de Segments (Segment Builder)** : Interface de création de règles de filtrage (ex: "Spécialité = Cardiologue" ET "Potentiel = A" ET "Objection = Trop cher"). Prévisualisation des résultats en temps réel.
- **Générateur de Rapports Marketing** : Outil de création de rapports sur mesure (sélection des indicateurs, période, format d'export).

---

## 3. Espace Directeur Commercial (Philippe)

### Endpoints Backend
- `GET /api/sales/dashboard` : KPIs commerciaux.
- `GET /api/objectives` : Liste des objectifs.
- `POST /api/objectives` : Définir un objectif.
- `GET /api/objectives/{id}` : Suivi d'un objectif.
- `GET /api/territories` : Liste des territoires et secteurs.
- `POST /api/territories` : Créer un secteur.
- `GET /api/territories/{id}` : Vue détaillée d'un secteur.
- `PUT /api/territories/{id}/assign` : Affecter un délégué.
- `GET /api/sales/forecasts` : Prévisions des ventes.
- `GET /api/teams` : Liste des équipes.
- `GET /api/teams/{id}` : Vue détaillée d'une équipe.

### Écrans Web (Wireframes)
- **Dashboard Direction Commerciale** : Vue d'hélicoptère (CA global, objectifs globaux), carte de France interactive (heatmap de performance par région), classements des régions.
- **Page de Gestion des Objectifs (Matrice)** : Grand tableau croisé (Lignes: Régions/Délégués, Colonnes: Produits/CA). Édition rapide dans les cellules (Inline editing).
- **Vue Détail d'un Objectif** : Courbe de progression (Réalisé vs Attendu sur le temps), liste des contributeurs.
- **Carte des Territoires & Secteurs** : Map interactive permettant de voir les zones, les points (hôpitaux, cabinets).
- **Gestionnaire de Secteurs** : Interface en split-screen (Liste des secteurs à gauche, médecins attribués à droite), outil de rééquilibrage de charge (drag & drop de médecins d'un secteur à l'autre).
- **Tableau de Bord Prévisions (Forecasts)** : Modélisation des ventes futures basées sur les historiques. Filtres par trimestre, mois, région, produit.
- **Annuaire des Équipes** : Vue hiérarchique (Organigramme interactif) avec Directeur régional -> Superviseur -> Délégués.
- **Vue Détail d'une Équipe** : Statistiques consolidées de l'équipe (Visites, Objectifs atteints), liste des membres, alertes spécifiques à l'équipe.
- **Générateur de Rapports Commerciaux** : Interface d'export pour la direction générale avec planificateur (envoi automatique par email tous les mois).

---

## 4. Espace Superviseur (Karim)

### Endpoints Backend
- (Endpoints précédents inclus)
- `GET /api/visits/reports/{id}` : Consulter un compte rendu.
- `GET /api/duos` : Liste des duos planifiés et passés.
- `GET /api/delegates/requests` : Liste des requêtes (congés, budget extra, etc.).

### Écrans Web (Wireframes)
- **Dashboard Superviseur** : Suivi "Aujourd'hui" (qui est où), alertes critiques (no-show de Nadia), jauges d'objectifs de l'équipe.
- **Page "Mon Équipe"** : Liste sous forme de cartes (Cards) pour chaque délégué avec photo, % d'objectif, statut actuel (En visite, Libre).
- **Profil Détaillé du Délégué (Vue Superviseur)** : Historique complet des visites du délégué, courbe de performance individuelle, ses objectifs, ses alertes.
- **Centre de Validation (Inbox)** : Interface type boîte mail. Liste des comptes rendus, ajouts de médecins, demandes de requêtes à gauche. Visualisation détaillée et boutons (Approuver/Rejeter/Commenter) à droite.
- **Calendrier de l'Équipe** : Vue calendrier consolidée (Agenda Google-like) affichant les tournées de tous les membres de l'équipe superposées (code couleur par délégué).
- **Gestionnaire de Coaching & Plans d'Actions** : Liste des plans d'actions par délégué. Modale de création d'un plan avec étapes, dates butoirs et indicateurs de succès.
- **Espace Duos (Accompagnement terrain)** : Liste des duos prévus. Formulaire de préparation (Choix délégué, Médecins ciblés, Motif). Formulaire post-duo (Évaluation, Points forts/faibles, Atteinte des objectifs).
- **Arbitrage de Portefeuille** : Interface spécifique pour gérer la limite (ex: 100 médecins max). Vue comparant les statistiques de deux médecins pour décider lequel retirer du portefeuille d'un délégué et à qui l'affecter.

---

## 5. Espace Délégué Médical (Sophie) - *Versions Web et Tablette*

### Endpoints Backend
- (Endpoints précédents inclus)
- `GET /api/visits/{id}` : Détail d'une visite prévue ou passée.
- `GET /api/prescribers/history/{id}` : Historique complet avec un médecin.

### Écrans Web/Tablette (Wireframes)
- **Dashboard Délégué (Accueil)** : Récapitulatif du jour : "Vous avez 5 visites aujourd'hui". Météo/Trafic (optionnel), jauges de primes/objectifs actuels, messages du superviseur.
- **Agenda / Plan de Tournée (Vue Jour/Semaine/Mois)** : Calendrier drag & drop pour planifier les visites. Vue "Liste" pour la tournée du jour avec intégration de carte (itinéraire).
- **Portefeuille "Mes Médecins"** : Liste avec filtres (Catégorie A,B,C, Spécialité, Ville). Indicateurs de fréquence (Pastilles couleur : à visiter urgemment, déjà visité).
- **Fiche Médecin 360° Complète** : 
  - Onglet "Général" : Coordonnées, établissement, notes, assistante.
  - Onglet "Historique" : Timeline de toutes les visites, comptes rendus passés.
  - Onglet "Prescriptions" : Produits prescrits, objections historiques.
  - Onglet "Statistiques" : Potentiel vs Fréquence de visite.
- **Création de Visite (Planification)** : Modale rapide (Choix du médecin, Date, Heure, Motif).
- **Exécution Visite (Avant)** : Fiche récapitulative "Avant d'entrer" avec la dernière objection du médecin et l'objectif de la visite.
- **Mode Présentation (CLM Viewer)** : Écran épuré (plein écran), navigation swipe (sur tablette) ou flèches (web). Boutons cachés/discrets pour ouvrir l'annexe ou revenir au menu.
- **Gestion des Échantillons & Matériel** : Interface de distribution : Tableau (Produit, Stock, Quantité à remettre), zone de signature électronique. Alerte de stock bas.
- **Saisie du Compte Rendu (CR)** : Formulaire optimisé (Menus déroulants, cases à cocher pour aller vite) : Objections (choix multiple), Intérêt (étoiles), Échantillons laissés. Champ de texte libre pour le mémo. Boutons : "Enregistrer Brouillon" / "Soumettre au Superviseur".
- **Saisie des Ventes/Indicateurs (Grossistes)** : Tableau mensuel permettant de saisir les sorties de stock des grossistes du secteur.
- **Déclaration Pharmacovigilance** : Formulaire rouge/alerte, champs obligatoires stricts (Produit, Lot, Description, Profil Patient, Gravité).
- **Mes Objectifs et Primes** : Jauges de progression (%), estimation financière de la prime en temps réel, classement amical (gamification optionnelle).

---

## 6. API Transverses (Global & Système)

### Endpoints Backend
- `GET /api/products` : Liste des produits.
- `GET /api/products/{id}` : Fiche identité détaillée du produit.
- `GET /api/establishments` : Liste des établissements.
- `GET /api/establishments/{id}` : Détails d'un établissement.
- `GET /api/notifications` : Liste paginée des notifications.
- `PUT /api/notifications/{id}/read` : Marquer comme lu.
- `GET /api/search?q={query}` : Recherche globale (multi-index).
- `GET /api/audit-trail` : Journal d'activités (Audit Trail).

### Écrans Web (Wireframes)
- **Référentiel Produits (Catalogue)** : Liste visuelle des produits du laboratoire. Fiches détaillées : DCI, Forme galénique, Conditionnement, Classe thérapeutique, Indications, Posologie, Effets secondaires (lecture seule pour délégués, éditable par région).
- **Annuaire des Établissements (Hôpitaux, Cliniques)** : Liste, vue détaillée affichant tous les médecins rattachés à l'établissement (principal et secondaire).
- **Centre de Notifications Dédié** : Page complète listant tout l'historique des notifications avec filtres (Non lues, Alertes urgentes, Validations).
- **Page de Recherche Globale** : Page de résultats enrichie façon moteur de recherche, avec onglets de tri (Résultats Médecins, Résultats Visites, Résultats Supports CLM).
- **Console d'Administration (Audit & Sécurité)** : Dashboard IT. Liste des logs (Audit Trail) avec filtres par date, utilisateur, type d'action. Export pour l'ANSM/RGPD. Configuration des accès et droits par rôles. Modale de gestion des laboratoires et produits.
