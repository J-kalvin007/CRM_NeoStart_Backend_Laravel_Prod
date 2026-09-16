# Mapping des APIs Backend pour l'Application Mobile

Ce document sert de réponse aux spécifications du fichier `backend_requirements.md`. 
Afin de préserver l'architecture universelle REST de notre CRM (utilisée par d'autres applications) et d'éviter la duplication de code, nous n'avons pas créé de routes spécifiques (comme `/api/v1/visites`). 
À la place, nous utilisons nos routes `apiResource` standardisées avec un système de filtrage performant. Le résultat et les données renvoyées sont exactement les mêmes.

La documentation complète (Swagger) a été mise à jour et est générée dans `api-docs.json`.

Voici comment l'application mobile doit appeler le backend pour satisfaire toutes les exigences de l'UI :

---

## 2. MODULE DÉLÉGUÉ - ACCUEIL & GÉOLOCALISATION

### 2.1 Page `DelegateDashboardView`
*   **Requis** : Liste des activités du jour
*   **Route Backend à utiliser** : 
    `GET /api/v1/activities?date_debut={YYYY-MM-DD}&date_fin={YYYY-MM-DD}` (en envoyant la date du jour).

### 2.2 Page `DelegateTourneeView`
*   **Requis** : Récupérer la tournée du jour
*   **Route Backend à utiliser** : 
    `GET /api/v1/tour-plans?date={YYYY-MM-DD}`
*   **Requis** : Optimisation IA des points de passage
*   **Route Backend à utiliser** : 
    `POST /api/v1/tour-plans/optimize`
    *Payload : `{"tour_plan_id": "uuid", "current_lat": 5.34, "current_lng": -4.01}`*

### 2.3 Page `DelegateVisitsView`
*   **Requis** : Liste des visites (avec filtrage: Aujourd'hui, Planifiées, Réalisées, Manquées)
*   **Route Backend à utiliser** : 
    `GET /api/v1/activities?activity_type=visite&statut={planifiee|realisee|manquee}`

---

## 3. MODULE VISITES & EXÉCUTION TERRAIN

### 3.1 Page `WeeklyPlanningView`
*   **Requis** : Plannings en cours
*   **Route Backend à utiliser** : 
    `GET /api/v1/tour-plans?date_debut={lundi}&date_fin={dimanche}`
*   **Requis** : Création d'une visite
*   **Route Backend à utiliser** : 
    `POST /api/v1/activities`
    *Payload : Doit inclure `"activity_type": "visite"`. La date (`date_activite`) ne peut pas être dans le passé.*

### 3.2 Exécution Terrain (Check-in / Check-out)
*   **Requis** : Démarrer / Terminer une visite et envoyer la géolocalisation.
*   **Route Backend à utiliser** : 
    `PUT /api/v1/activities/{id}`
    *   **Pour Démarrer (Check-in)** : Envoyer `statut: "en_cours"`, `heure_debut`, `gps_debut_lat`, `gps_debut_lng`.
    *   **Pour Terminer (Check-out)** : Envoyer `statut: "realisee"`, `heure_fin`, `gps_fin_lat`, `gps_fin_lng`, `notes`. (Une fois en statut "realisee", l'activité est verrouillée en base).

---

## 4. MODULE MÉTIER - ADV & PHARMACOVIGILANCE

### 4.1 Page `DelegateDigitalSupportView`
*   **Requis** : Liste des documents interactifs E-Detailing
*   **Route Backend à utiliser** : 
    `GET /api/v1/digital-supports`

### 4.2 Page `PharmacovigilanceView`
*   **Requis** : Déclaration d'effet indésirable
*   **Route Backend à utiliser** : 
    `POST /api/v1/pharmacovigilance-reports`
    *Payload : `contact_id`, `product_id`, `patient_initials`, `adverse_event_description`, `report_date`, etc.*

---

## 5. MESSAGERIE / CHAT
*   **Requis** : Historique et envoi de messages
*   **Routes Backend existantes (à développer/ajouter selon vos besoins côté routeur si non standard)** :
    L'architecture a été posée (Tables `conversations`, `messages`, `conversation_user`). Les API devront pointer vers des requêtes GET/POST sur un contrôleur `MessageController` (ou via WebSockets/Pusher pour du temps réel).
