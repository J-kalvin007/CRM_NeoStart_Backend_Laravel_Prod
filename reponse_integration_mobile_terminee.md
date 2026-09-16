# Réponse : Intégration Mobile Backend Terminée

La totalité des tâches requises pour l'intégration du backend avec l'application mobile (Flutter) a été réalisée avec succès en respectant les normes architecturales, le RBAC, et les standards Laravel.

## 1. Architecture de Notification Multi-Canaux
Un service de notifications unifié (`NotificationService`) a été mis en place. Il s'appuie sur la classe `SystemNotification` pour dispatcher simultanément les alertes via :
- **In-App** : Base de données Laravel pour l'historique.
- **Push Mobile (FCM)** : Firebase Cloud Messaging (via le service `FcmNotificationService`).
- **Email** : Envoi de mails transactionnels.

*Exemple d'utilisation intégré : Lorsqu'un délégué distribue des échantillons et que le stock passe sous le seuil critique (`stock_alerte`), une notification `low_stock` est immédiatement envoyée à son manager.*

## 2. Moteur de Synchronisation Offline (SyncController)
Le contrôleur `/api/v1/sync` a été entièrement réécrit pour supporter le mode déconnecté du mobile :
- **Pull (`/sync/pull`)** : Renvoie toutes les données modifiées depuis le `last_sync` (Contacts, Accounts, Products, Activities, Events, etc.). La gestion des données supprimées est désormais incluse via le nœud JSON `deleted` qui traque les `deleted_at`. **Important :** les données sont automatiquement filtrées selon le territoire du délégué grâce au `PerimeterScope`.
- **Push (`/sync/push`)** : Traite les créations/modifications hors-ligne ligne par ligne pour éviter qu'une erreur sur une entité ne fasse échouer tout le lot. L'idempotence est garantie par l'utilisation de `local_uuid`.

## 3. Activités et Comptes Rendus (Workflows)
Les visites médicales ont été étendues pour gérer des comptes rendus professionnels :
- La table `activities` possède de nouvelles colonnes (`report_status`, `report_content`, `validated_by_id`, `rejection_reason`).
- Une table pivot `activity_document` permet au délégué d'indiquer précisément les supports e-detailing (documents, vidéos) présentés lors de la visite, avec le temps d'affichage.
- Un nouveau contrôleur `ActivityReportController` gère la soumission et la validation des rapports par les managers, déclenchant automatiquement les notifications adéquates au passage.

## 4. Échantillons et Stocks
Le `SampleMovementController` intègre désormais une **vérification stricte**. Toute tentative de sortie (distribution) supérieure au stock réel disponible en mémoire est rejetée (`HTTP 422`).

## 5. Ressources JSON (API Responses)
Les `JsonResource` (Contact, Account, Product, Activity) ont été mis à jour pour correspondre scrupuleusement aux besoins du frontend mobile (inclusion systématique des `local_uuid`, des nœuds enfants comme la forme galénique, et des relations).

## 6. Fichiers & Médias
Création d'un `MediaController` unifié (`/api/v1/media/upload`) permettant l'envoi de pièces jointes (photos, signatures électroniques des médecins, reçus de notes de frais) vers le dossier public avec génération de nom unique (`uuid`).

## 7. Documentation Swagger & README
- Le fichier global `README.md` a été mis à jour pour refléter les nouvelles capacités (Sync, Notifications, Lookups).
- L'ensemble des nouvelles routes (Sync, Media, Reports) a été minutieusement annoté avec les attributs PHP 8 (`#[OA\Get]`, `#[OA\Post]`, etc.).
- Le cache Swagger a été régénéré avec succès via `php artisan l5-swagger:generate`. La documentation est à jour sur `/api/documentation`.
