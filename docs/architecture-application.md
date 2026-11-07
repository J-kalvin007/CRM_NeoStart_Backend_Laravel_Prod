# Architecture observée et conditions d'exécution

Ce document décrit l'application telle qu'elle existe dans le dépôt. Il ne propose pas de la modifier. Les fichiers PHP, les migrations, les routes et `.env.example` n'ont pas été changés.

## Rôle de l'application

C'est une API HTTP Laravel. Le client prévu par le dépôt est une application mobile, notamment Flutter, avec une synchronisation hors ligne. Il n'y a pas d'interface métier à servir : la page `/` affiche seulement la page d'accueil Laravel. Les endpoints utiles commencent par `/api`.

## Stack constatée

| Élément | Valeur observée |
| --- | --- |
| Langage | PHP. `composer.json` demande `^8.2`. Le `composer.lock` exige PHP 8.3 pour des paquets Firebase (`beste/clock`, `beste/in-memory-cache-php`). |
| Framework | Laravel 12.69.2, d'après `composer.lock` |
| Authentification API | Laravel Sanctum 4, jetons personnels |
| Rôles | `spatie/laravel-permission` |
| Journal d'activité | `spatie/laravel-activitylog` |
| Documentation OpenAPI | `darkaonline/l5-swagger`. Le fichier généré n'est pas versionné. |
| PDF | `dompdf/dompdf` |
| Excel | `phpoffice/phpspreadsheet` |
| Notifications push | `kreait/laravel-firebase`. Aucun fichier `firebase-credentials.json` n'est présent dans le dépôt. |
| Base | MySQL. `DB_CONNECTION=mysql` dans `.env.example`. Le README du projet indique MySQL 8 ou plus. |
| Sessions, cache, files d'attente | Pilote `database` dans `.env.example` |
| E-mail local | `MAIL_MAILER=log` : les messages sont écrits dans les journaux, ils ne partent pas |
| Front de build | `package.json` contient Vite. L'API n'en a pas besoin pour répondre. Node.js n'est pas installé dans l'image. |

L'image Docker utilise PHP 8.3, qui satisfait à la fois `composer.json` et le verrou. Ni `composer.json` ni `composer.lock` ne sont modifiés. Les dépendances sont installées avec `composer install` à partir du verrou, jamais avec `composer update`.

## Organisation utile pour l'exécution

- `public/index.php` est le point d'entrée HTTP.
- `routes/api.php` déclare l'API sous le préfixe Laravel `/api`, puis le préfixe `v1`.
- `routes/web.php` ne déclare que `/`.
- `bootstrap/app.php` enregistre aussi la sonde `GET /up`.
- `app/Http/Controllers/Api/V1` contient les contrôleurs.
- `database/migrations` contient le schéma. Plusieurs migrations utilisent du SQL propre à MySQL (`ALTER TABLE ... ENUM`, `MODIFY COLUMN`, jointures `UPDATE`). Elles ne sont pas réécrites.
- `config/database.php` fixe le moteur InnoDB, le jeu `utf8mb4` et la collation `utf8mb4_unicode_ci` pour la connexion `mysql`.
- `config/cors.php` autorise déjà toutes les origines sur `api/*`. Une application Flutter native n'utilise pas CORS. Ce fichier n'a pas été modifié.

## Authentification réelle

La connexion n'est pas une session navigateur.

1. `POST /api/v1/login` avec `email` et `password`.
2. Si le compte a `force_password_change`, la réponse est `403` avec `status = requires_password_change` et `user_id`. Aucun jeton n'est émis.
3. Si `otp_enabled` est vrai, un code à 6 chiffres est mis en cache 10 minutes et envoyé par e-mail. La réponse est `202` avec `status = requires_otp`. Le jeton n'est émis qu'après `POST /api/v1/verify-otp`.
4. Sinon, la réponse `200` contient `token` et `user`.
5. Les routes protégées exigent `Authorization: Bearer <token>`.
6. `POST /api/v1/logout` supprime le jeton courant. Il n'existe pas d'endpoint de rafraîchissement. `config/sanctum.php` a `expiration = null` : le jeton ne expire pas tout seul.
7. La réinitialisation de mot de passe supprime tous les jetons de l'utilisateur.

`EnsureActiveUser` refuse un utilisateur dont `status` vaut `false`. `RestrictReferenceWrites` réserve l'écriture de certains référentiels aux rôles d'administration et de direction.

Le périmètre des données est appliqué dans `App\Models\Scopes\DynamicPerimeterScope` à partir du pays et du rôle de l'utilisateur connecté. Le header réellement lu par plusieurs contrôleurs est `X-Country-Id`. Le header `X-Organizational-Unit-Id` est recopié lors du rejeu interne de `sync/push`. Le commentaire en tête de `bootstrap/app.php` parle d'un filtre par unité organisationnelle, mais le middleware branché est `RestrictReferenceWrites`, qui ne lit pas ce header.

## Données et fichiers

- Les tables `sessions`, `cache` et `jobs` sont créées par les migrations `0001_01_01_*`. Elles sont nécessaires parce que `.env.example` utilise ces pilotes.
- Les jetons Sanctum utilisent la table `personal_access_tokens`, créée par une migration existante.
- `FILESYSTEM_DISK=local` pointe vers `storage/app/private`.
- L'upload public (`POST /api/v1/media/upload`) enregistre sur le disque `public`, donc `storage/app/public`, et renvoie une URL qui suppose le lien `public/storage`.
- Les pièces de congés et les pièces jointes de messagerie sont lues depuis le disque `local`.
- Sans disque persistant, un redéploiement efface ces fichiers. Les lignes en base, elles, restent dans MySQL.

## Notifications

Plusieurs notifications implémentent `ShouldQueue`. Avec `QUEUE_CONNECTION=database`, elles restent dans la table `jobs` tant qu'un processus `php artisan queue:work` ne tourne pas. Les e-mails de connexion OTP et de mot de passe sont envoyés directement, pas via la file. Avec `MAIL_MAILER=log`, leur contenu est dans les journaux.

Les notifications push dépendent d'un fichier de compte de service Firebase indiqué par `FIREBASE_CREDENTIALS`. Ce fichier est absent du dépôt et ne doit pas être commité.

## Ce qui n'est pas lancé automatiquement

- Aucune tâche planifiée n'est enregistrée dans `app` (`Schedule::` n'y apparaît pas).
- `DatabaseSeeder` crée des rôles et des référentiels. Il n'appelle pas `UserSeeder`.
- `UserSeeder` crée des comptes locaux dont le mot de passe est `password`, et il contient des adresses e-mail réelles. Il ne doit pas être exécuté en production.

## SQLite

`phpunit.xml` force déjà les tests PHPUnit vers SQLite en mémoire (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Ce réglage ne concerne pas l'exécution ni Render.

Le schéma n'est pas traité comme compatible SQLite : des migrations exécutent du SQL MySQL. SQLite n'est donc pas le moteur de l'environnement Docker, ni celui de la production. Les tests PHPUnit, s'ils sont lancés, restent isolés de MySQL par `phpunit.xml`. Ils ne constituent pas une validation du schéma MySQL.

## Prérequis machine

Docker Engine avec Compose v2, ou Docker Desktop. PHP, Composer, Laravel et MySQL ne sont pas requis sur Windows. Sur la machine utilisée pour préparer ces fichiers, la commande `docker` était absente du PATH et `docker.exe` n'était pas installé. Les conteneurs n'ont donc pas été démarrés ici.
