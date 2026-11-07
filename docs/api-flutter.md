# API pour l'application Flutter

L'application Flutter appelle le backend en HTTPS. Aucun endpoint n'a été ajouté ni modifié. Les chemins ci-dessous sont ceux de `routes/api.php` et de `bootstrap/app.php`.

## URL de base

| Environnement | Origine | Exemple d'appel |
| --- | --- | --- |
| Docker local | `http://127.0.0.1:8080` | `http://127.0.0.1:8080/api/v1/login` |
| Render | l'URL HTTPS du service web, la même valeur que `APP_URL` | `https://<service>.onrender.com/api/v1/login` |

Il n'existe pas encore d'URL de production tant que le service n'est pas créé dans Render. Flutter doit recevoir cette origine par configuration, pas en dur dans le code métier si vous avez plusieurs environnements.

Le préfixe de l'API est `/api`. Le groupe métier est `/api/v1`. Une application native n'envoie pas de cookie CORS. Le fichier `config/cors.php` n'a pas été changé.

## Headers

Pour une requête JSON :

```http
Accept: application/json
Content-Type: application/json
```

Pour une route protégée, ajoutez le jeton reçu au login, sans guillemets et sans le préfixe `Bearer` répété :

```http
Authorization: Bearer 1|le-secret-renvoyé-dans-token
```

`X-Country-Id` est lu par des contrôleurs de comptes, contacts, produits et configuration. Envoyez-le seulement si l'écran doit filtrer un pays. Ce n'est pas un remplacement du jeton.

Les réponses d'erreur de validation Laravel ont en général cette forme, avec le code `422` :

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Identifiants incorrects"]
  }
}
```

Le message exact dépend de la règle qui échoue. Les messages écrits dans les contrôleurs d'authentification sont en français lorsque le code les définit ainsi.

Une erreur SQL connue de l'application répond en JSON avec `code` égal à `DATABASE_SCHEMA_NOT_READY` (HTTP 503) ou `DATABASE_ERROR` (HTTP 500). Ce comportement est déjà dans `bootstrap/app.php`.

## Authentification

Il n'y a pas de rafraîchissement de jeton. Sanctum est configuré avec `expiration = null`. Le jeton reste valable jusqu'à la déconnexion, jusqu'à sa suppression, ou jusqu'à une réinitialisation de mot de passe, qui supprime tous les jetons de l'utilisateur. Flutter le conserve dans un stockage sûr de l'appareil et l'envoie dans `Authorization`.

Le nom interne du jeton créé par le code est `auth-token`. La valeur à stocker est le champ JSON `token`, déjà au format `identifiant|secret`.

### Connexion

`POST /api/v1/login`

Corps JSON obligatoire : `email`, `password`. Limite : 10 requêtes par minute.

Réponses observées dans `AuthController` :

| Code | Quand | Corps |
| --- | --- | --- |
| 200 | Mot de passe correct, compte actif, OTP désactivé, pas de changement de mot de passe forcé | `token`, `user` (rôles, permissions, pays, manager, poste) |
| 202 | OTP activé | `status` = `requires_otp`, `message`, `user_id`. Pas de jeton. |
| 403 | Changement de mot de passe obligatoire | `status` = `requires_password_change`, `message`, `user_id` |
| 403 | Compte désactivé | message d'interdiction |
| 422 | E-mail ou mot de passe refusé | erreur de validation, message `Identifiants incorrects` |

### Changement du mot de passe temporaire

`POST /api/v1/auth/change-password`

Corps : `user_id` (UUID), `old_password`, `new_password`, `new_password_confirmation`. Le nouveau mot de passe a au moins 8 caractères. Limite : 10 par minute.

- `200` : mot de passe mis à jour et OTP désactivé. Corps : `message`, `token`, `user`.
- `202` : mot de passe mis à jour et OTP activé. Corps : `status` = `requires_otp`, `message`, `user_id`.
- `422` : ancien mot de passe incorrect ou confirmation invalide.

### Vérification OTP

`POST /api/v1/verify-otp`

Corps : `user_id`, `otp_code` (exactement 6 caractères). Limite : 10 par minute.

- `200` : `token`, `user`.
- `401` : code absent, différent ou expiré. Le code vit 10 minutes dans le cache.
- `404` : utilisateur introuvable.
- `403` : compte inactif ou changement de mot de passe encore exigé.

Avec `MAIL_MAILER=log`, le code n'arrive pas dans une boîte mail. Il est écrit dans les journaux du conteneur. En production, un vrai SMTP est nécessaire pour que le téléphone reçoive le code. Voir le guide Render.

### Renvoi du code

`POST /api/v1/resend-otp`

Corps : `user_id`. Limite : 3 par minute. Le code n'est renvoyé que si un OTP de connexion est déjà en attente. `200` avec `status` = `success`, `message`, `user_id`. `503` si l'envoi d'e-mail échoue. `404` si l'utilisateur n'existe pas.

### Mot de passe oublié

1. `POST /api/v1/password/email` avec `email`. Limite : 3 par minute. La réponse `200` a le même texte que le compte existe ou non, afin de ne pas révéler les comptes. Le jeton de réinitialisation est une chaîne aléatoire de 60 caractères, conservée une heure. Il est envoyé par e-mail, pas retourné dans le JSON.
2. `POST /api/v1/password/verify` avec `email` et `token`. `200` si le jeton correspond. `422` sinon.
3. `POST /api/v1/password/reset` avec `email`, `token`, `password`, `password_confirmation`. Mot de passe d'au moins 8 caractères. `200` si le mot de passe est changé. Tous les jetons Sanctum de cet utilisateur sont supprimés : Flutter doit effacer le jeton local et renvoyer l'utilisateur vers la connexion.

### Session courante

Toutes ces routes exigent le jeton.

| Méthode | Chemin | Corps | Réponse |
| --- | --- | --- | --- |
| GET | `/api/v1/me` | aucun | l'utilisateur, avec rôles, permissions, pays, manager et poste |
| PATCH | `/api/v1/me` | `name`, `email`, `phone` optionnel | l'utilisateur mis à jour |
| POST | `/api/v1/logout` | aucun | `message` = `Déconnexion réussie`. Le jeton courant est supprimé. |
| POST | `/api/v1/auth/toggle-otp` | `otp_enabled` booléen | `message`, `otp_enabled` |
| POST | `/api/v1/user/fcm-token` | `fcm_token` | `message` = `FCM Token updated successfully` |
| POST | `/api/v1/auth/invite` | selon la validation du contrôleur d'invitation | création d'un compte par un administrateur |

`GET /api/login` n'est pas la connexion. C'est une route JSON qui répond `401` avec `message` = `Non authentifié`. Flutter doit utiliser `POST /api/v1/login`.

Un jeton absent ou invalide sur une route protégée est refusé par Sanctum. Le message Laravel habituel est `Unauthenticated.`

## Fichiers

`POST /api/v1/media/upload` est protégé. Ce n'est pas du JSON.

```http
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

Champs : `file` obligatoire, `folder` optionnel parmi `signatures`, `avatars`, `documents`, `reports`. Types acceptés : jpg, jpeg, png, webp, pdf, mp4, webm, ppt, pptx, doc, docx, xls, xlsx. Taille maximale : 10 Mo. Nginx et PHP acceptent jusqu'à 16 Mo dans l'image Docker, la limite métier reste 10 Mo.

Réponse `200` : `url`, `path`, `filename`. L'URL est absolue. Derrière Render, elle peut être en `http` tant que les proxies de confiance ne sont pas déclarés. Voir la dernière section du guide Render.

Téléchargements protégés, en GET avec le jeton :

- `/api/v1/leaves/{id}/documents/{document}`
- `/api/v1/conversations/{id}/messages/{message}/attachments/{attachment}`

La réponse est le fichier, pas un JSON. Le header `Content-Disposition` est exposé par la configuration CORS existante.

Imports de fichiers, protégés, en `multipart/form-data` :

| Méthode | Chemin | Rôle |
| --- | --- | --- |
| POST | `/api/v1/contacts/import` | import de contacts |
| GET | `/api/v1/contacts/import/template` | modèle à télécharger |
| POST | `/api/v1/accounts/import` | import d'établissements |
| GET | `/api/v1/accounts/import/template` | modèle |
| POST | `/api/v1/products/import` | import de produits |
| GET | `/api/v1/products/import/template` | modèle |

Les noms de champs du fichier sont ceux validés dans chaque contrôleur d'import. Ils n'ont pas été changés.

## Synchronisation hors ligne

Les deux routes sont protégées.

`GET /api/v1/sync/pull?last_sync=2026-01-01 00:00:00`

`last_sync` est optionnel et doit être une date. Sans valeur, le serveur part du 1er janvier 1970. Réponse `200` :

- `timestamp` : horloge du serveur à conserver pour le prochain pull ;
- `data` : enregistrements modifiés depuis `last_sync` pour les contacts, comptes, produits, activités, plans de tournée, tâches, documents, supports digitaux, événements, notifications de l'utilisateur, mouvements d'échantillons, campagnes, opportunités, commandes, ventes, notes de frais, congés, formations et objectifs ;
- `deleted` : identifiants supprimés pour les contacts, comptes, activités, tâches, plans de tournée et objectifs.

`POST /api/v1/sync/push`

Corps : `queue`, tableau d'au plus 100 éléments. Chaque élément a `endpoint` (obligatoire), `method` (`POST`, `PUT`, `PATCH` ou `DELETE`) et `payload` (objet). L'endpoint est relatif à `/api/v1/` et doit commencer par l'une de ces ressources : `activities`, `contacts`, `accounts`, `expenses`, `events`, `tasks`, `tour-plans`, `sample-movements`, `sample-requests`, `requests`, `leaves`, `trainings`, `orders`, `sales`, `opportunities`, `objectifs`, `clm-analytics`, `pharmacovigilance-reports`.

Pour une modification, le payload peut contenir `client_updated_at` ou `updated_at`. Si le serveur est plus récent et que `force_override` n'est pas vrai, l'élément est refusé comme conflit.

Réponse `200` : `status` (`success` ou `partial_success`), `processed_items`, `errors`, `timestamp`. Un échec global de la transaction répond `500`.

## Pagination

Lorsque le contrôleur appelle `paginate`, la réponse suit le paginateur Laravel : un objet avec `data`, puis `links` et `meta` (`current_page`, `last_page`, `per_page`, `total`). Le paramètre de requête `per_page` est honoré là où le contrôleur le lit. Défauts vus dans le code : 15 pour les notes de frais, opportunités, ventes et tâches ; 25 pour les mouvements d'échantillons, la pharmacovigilance, les produits et les objectifs ; 30 pour les plans de tournée ; 50 pour les événements. Les plafonds varient selon la méthode. Les autres listes peuvent avoir leur propre défaut : il est dans le contrôleur correspondant, pas dans une convention unique.

## Routes protégées

Sauf mention contraire, le jeton est obligatoire. Une ressource `apiResource` expose :

| Méthode | Chemin | Action |
| --- | --- | --- |
| GET | `/api/v1/{ressource}` | liste |
| POST | `/api/v1/{ressource}` | création |
| GET | `/api/v1/{ressource}/{id}` | détail |
| PUT et PATCH | `/api/v1/{ressource}/{id}` | mise à jour |
| DELETE | `/api/v1/{ressource}/{id}` | suppression |

Les paramètres d'URL utilisent le singulier Laravel : `{account}`, `{contact}`, `{activity}`, et ainsi de suite.

Les écritures de référentiels suivantes sont refusées avec `403` si l'utilisateur n'a ni un rôle d'administration ou de direction, ni la permission `scope:global` : `config/`, `settings`, `branding`, spécialités, segmentations, potentiels, influences, formes galéniques, types et résultats d'activité, formats d'événement, types d'objectif, catégories, laboratoires, classes thérapeutiques, produits, supports digitaux, champs personnalisés, pays, rôles et permissions. La lecture reste ouverte à un utilisateur connecté.

### Référentiels et organisation

Ressources complètes : `countries`, `specialities`, `segmentations`, `potentials`, `influences`, `galenic-forms`, `activity-types`, `activity-results`, `event-formats`, `duo-activity-types`, `trio-activity-types`, `categories`, `laboratories`, `therapeutic-classes`, `products`, `custom-fields`, `roles`, `objectif-types`.

`field-posts` n'a pas de route de détail. `kol-profiles` n'a que `GET /api/v1/kol-profiles`.

| Méthode | Chemin | Rôle |
| --- | --- | --- |
| GET | `/api/v1/settings` | public, sans jeton |
| GET | `/api/v1/branding` | public, sans jeton |
| POST | `/api/v1/config/settings` | enregistrement de paramètres |
| GET, POST | `/api/v1/config/account-types` et `contact-types` et `product-types` | liste et création |
| PUT, DELETE | `/api/v1/config/{type}/{id}` | mise à jour et suppression de ces trois types |
| PUT | `/api/v1/branding/name` | nom de l'application |
| POST, DELETE | `/api/v1/branding/logo` | logo |
| POST, DELETE | `/api/v1/branding/favicon` | favicon |
| GET | `/api/v1/configurations` | configuration agrégée |
| GET | `/api/v1/account-types` | types de comptes |
| GET | `/api/v1/permissions` | liste des permissions |
| GET | `/api/v1/permissions/grouped` | permissions groupées |
| GET, POST | `/api/v1/roles/{roleId}/permissions` | lecture et attribution |

### Utilisateurs

Ressource complète `users`, plus :

| Méthode | Chemin |
| --- | --- |
| GET | `/api/v1/users/locations` |
| POST | `/api/v1/users/bulk-update-country` |
| POST | `/api/v1/me/location` |
| GET | `/api/v1/users/{id}/locations` |
| POST | `/api/v1/users/{user}/products` |

### Comptes, contacts, produits

Ressources complètes : `accounts`, `contacts`, `products`.

| Méthode | Chemin |
| --- | --- |
| POST | `/api/v1/accounts/check-duplicates` |
| POST | `/api/v1/accounts/bulk-update` |
| POST | `/api/v1/accounts/bulk-delete` |
| POST | `/api/v1/accounts/{account}/valider` |
| POST | `/api/v1/accounts/{account}/rejeter` |
| POST | `/api/v1/accounts/{account}/validate` |
| POST | `/api/v1/contacts/check-duplicates` |
| POST | `/api/v1/contacts/arbitrage` |
| DELETE | `/api/v1/contacts/bulk-delete` |
| POST | `/api/v1/contacts/bulk-update` |
| POST | `/api/v1/contacts/{contact}/valider` |
| POST | `/api/v1/contacts/{contact}/rejeter` |
| POST | `/api/v1/contacts/{contact}/validate` |
| GET | `/api/v1/contacts/{contact}/reports` |
| GET | `/api/v1/contacts/{contact}/visit-reports` |
| GET | `/api/v1/contacts/{contact}/monthly-history` |
| GET | `/api/v1/contacts/{contact}/presented-documents` |
| GET | `/api/v1/products/{product}/objections` |

Les corps de création et de mise à jour sont les règles de `StoreAccountRequest`, `UpdateAccountRequest`, `StoreContactRequest`, `UpdateContactRequest`, `StoreProductRequest` et `UpdateProductRequest`.

### Activités, tournées, tâches

Ressource complète : `activities`, `tour-plans`, `tasks`.

| Méthode | Chemin |
| --- | --- |
| POST | `/api/v1/activities/trio` et `/api/v1/trio` |
| POST | `/api/v1/activities/{activity}/validate-trio` |
| POST | `/api/v1/activities/{activity}/reject-trio` |
| POST | `/api/v1/trio/{id}/valider` et `/api/v1/trio/{id}/rejeter` |
| POST | `/api/v1/activities/duo` et `/api/v1/duo` |
| POST | `/api/v1/activities/{activity}/accept-duo` |
| POST | `/api/v1/activities/{activity}/refuse-duo` |
| POST | `/api/v1/activities/{activity}/join-duo` |
| PATCH | `/api/v1/activities/{activity}/duo` |
| DELETE | `/api/v1/activities/{activity}/duo` |
| POST | `/api/v1/duo/{id}/accepter` et `/api/v1/duo/{id}/refuser` |
| POST | `/api/v1/activities/{activity}/validate` |
| POST | `/api/v1/activities/bulk-validate` |
| DELETE et POST | `/api/v1/activities/bulk-delete` |
| POST | `/api/v1/activities/{activity}/reschedule` |
| POST | `/api/v1/activities/{activity}/start` |
| POST | `/api/v1/activities/{activity}/complete` |
| PUT | `/api/v1/activities/{activity}/report` |
| POST | `/api/v1/activities/{activity}/report/validate` |
| POST | `/api/v1/activities/{activity}/clm-analytics` |
| POST | `/api/v1/activities/{activity}/objections` |
| POST | `/api/v1/tour-plans/optimize` |
| GET | `/api/v1/planning-options` |

La création et la mise à jour d'activité passent par `StoreActivityRequest` et `UpdateActivityRequest`. Les tâches passent par `StoreTaskRequest` et `UpdateTaskRequest`. Les tournées passent par `StoreTourPlanRequest` et `UpdateTourPlanRequest`.

### Commercial, marketing, stocks

Ressources complètes : `opportunities`, `orders`, `sales`, `events`, `documents`, `digital-supports`, `pharmacovigilance-reports`, `sample-movements`, `expenses`.

`stocks` pointe vers le même contrôleur que `sample-movements`.

| Méthode | Chemin |
| --- | --- |
| GET | `/api/v1/my-stock` |
| GET, POST | `/api/v1/sample-requests` |
| PUT, DELETE | `/api/v1/sample-requests/{id}` |
| POST | `/api/v1/sample-requests/{id}/approve` et `.../reject` |
| POST | `/api/v1/expenses/{expense}/submit` |
| POST | `/api/v1/expenses/{expense}/validate` |
| POST | `/api/v1/expenses/{expense}/reject` |
| POST | `/api/v1/expenses/{expense}/reimburse` |
| PATCH | `/api/v1/expenses/{expense}/status` |

Les notes de frais utilisent `StoreExpenseRequest` et `UpdateExpenseRequest`. Les mouvements d'échantillons utilisent `StoreSampleMovementRequest`.

### Pilotage

| Méthode | Chemin |
| --- | --- |
| GET | `/api/v1/reports/overview` |
| POST | `/api/v1/reports/document` |
| GET | `/api/v1/delegates` |
| GET | `/api/v1/delegates/{id}/itinerary` |
| GET | `/api/v1/delegates/{id}/overview` |
| GET | `/api/v1/analytics/executive` |
| GET | `/api/v1/analytics/performance` |
| GET | `/api/v1/analytics/team-performance` |
| GET | `/api/v1/analytics/edetailing-summary` |
| GET | `/api/v1/export/performance` |
| GET | `/api/v1/export/expenses` |
| GET | `/api/v1/export/activities` |
| GET | `/api/v1/export/activities/pdf` |
| GET | `/api/v1/marketing/objections-stats` |
| GET | `/api/v1/supervisor/alerts` |
| GET | `/api/v1/supervisor/dashboard` |
| GET | `/api/v1/supervisor/team-planning` |
| GET | `/api/v1/supervisor/missing-reports` |
| POST | `/api/v1/supervisor/remind-missing-reports` |
| GET | `/api/v1/kols` |
| GET | `/api/v1/indicators` |
| GET | `/api/v1/me/objectifs-summary` |
| GET | `/api/v1/objectifs/{objectif}/history` |

`objectifs` est une ressource complète. `POST /api/v1/reports/document` est limité à 20 requêtes par minute. Les exports Excel et PDF répondent par un fichier. Ils échouent en `503` si les classes Dompdf ou PhpSpreadsheet sont absentes. L'image Docker les installe à partir du `composer.lock`.

### Conformité, validations, coaching

| Méthode | Chemin |
| --- | --- |
| GET | `/api/v1/compliance/anomalies` |
| POST | `/api/v1/compliance/anomalies/{id}/{action}` |
| GET, PATCH | `/api/v1/compliance/rules` |
| GET | `/api/v1/validations/pending` |
| POST | `/api/v1/validations/bulk` |
| POST | `/api/v1/validations/{type}/{id}/approve` |
| POST | `/api/v1/validations/{type}/{id}/reject` |
| GET | `/api/v1/coaching/delegates/{id}` |
| GET | `/api/v1/coaching/my-evaluations` |
| GET | `/api/v1/coaching/duo-dashboard` |
| GET | `/api/v1/coaching/trio-dashboard` |
| POST | `/api/v1/coaching/evaluate` |
| GET, POST | `/api/v1/coaching-sessions` |
| GET, PUT, DELETE | `/api/v1/coaching-sessions/{id}` |
| PUT | `/api/v1/coaching-sessions/{id}/complete` |
| POST | `/api/v1/coaching-sessions/{id}/refuse` |
| GET | `/api/v1/export/duos` et `/api/v1/export/duos/pdf` |
| GET | `/api/v1/export/trios` et `/api/v1/export/trios/pdf` |

### Congés, formations, messages, support

| Méthode | Chemin |
| --- | --- |
| GET, POST | `/api/v1/leaves` |
| GET, PUT, DELETE | `/api/v1/leaves/{id}` |
| PATCH | `/api/v1/leaves/{id}/status` |
| POST | `/api/v1/leaves/{id}/validate` et `.../reject` |
| POST | `/api/v1/leaves/{id}/submit` et `.../decision` |
| GET | `/api/v1/leaves/employees` |
| PUT | `/api/v1/leaves/employee-settings` |
| GET | `/api/v1/leaves/options` |
| GET | `/api/v1/leaves/calendar` |
| GET, POST | `/api/v1/leaves/balances` |
| POST | `/api/v1/leaves/carry-over` |
| GET, PUT | `/api/v1/leaves/policy` |
| GET | `/api/v1/trainings` |
| GET | `/api/v1/trainings/{id}` |
| PATCH | `/api/v1/trainings/{id}/progress` |
| GET | `/api/v1/conversations/recipients` |
| GET, POST | `/api/v1/conversations` |
| PATCH | `/api/v1/conversations/{id}/participants` |
| GET, POST | `/api/v1/conversations/{id}/messages` |
| GET | `/api/v1/faqs` |
| GET | `/api/v1/faq-categories` |
| GET, POST | `/api/v1/support/tickets` |
| POST | `/api/v1/support/reports` |
| GET | `/api/v1/support/tickets/{id}` |
| POST | `/api/v1/support/tickets/{id}/messages` |
| GET, POST | `/api/v1/notifications` |
| POST | `/api/v1/notifications/mark-all-as-read` |
| POST | `/api/v1/notifications/{id}/mark-as-read` |
| DELETE | `/api/v1/notifications/{id}` |

`requests` est une ressource sans route de détail : liste, création, mise à jour, suppression.

`campaigns` est une ressource complète, plus `PATCH /api/v1/campaigns/{campaign}/status`, `POST /api/v1/campaigns/{campaign}/products` et `DELETE /api/v1/campaigns/{campaign}/products/{product}`.

## Ordre pratique côté Flutter

1. Renseigner l'origine HTTPS de production.
2. Appeler `POST /api/v1/login`.
3. Si la réponse est `403` avec `requires_password_change`, enchaîner sur `POST /api/v1/auth/change-password`.
4. Si la réponse est `202` avec `requires_otp`, demander le code à l'utilisateur et appeler `POST /api/v1/verify-otp`.
5. Stocker `token`.
6. Envoyer `Authorization` sur toutes les routes du tableau protégé, y compris `GET /api/v1/me` au démarrage.
7. Enregistrer le jeton Firebase avec `POST /api/v1/user/fcm-token` si les notifications push sont configurées côté serveur.
8. Pour le mode hors ligne, pull avec le dernier `timestamp`, puis push de la file locale.
9. À la déconnexion, appeler `POST /api/v1/logout` puis effacer le jeton local.

La liste exacte peut être revérifiée, une fois les conteneurs démarrés, avec :

```powershell
powershell -File scripts/artisan.ps1 route:list --path=api
```

Des attributs OpenAPI existent déjà sur une partie des contrôleurs. L'interface n'est pas générée dans le dépôt. La commande, dans le conteneur, est `php artisan l5-swagger:generate`. L'interface serait ensuite servie sur `/api/documentation`. Elle n'a pas été générée ici, et elle ne remplace pas les contrôleurs comme source du contrat.
