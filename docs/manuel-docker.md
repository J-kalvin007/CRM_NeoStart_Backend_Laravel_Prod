# Manuel Docker local

Ce guide fait tourner le backend déjà présent dans le dépôt. Il ne demande pas d'installer PHP, Composer, Laravel ou MySQL sur Windows. Le seul logiciel à installer est Docker Desktop, ou Docker Engine avec le plugin Compose v2.

Les conteneurs de ce fichier `docker-compose.yml` servent au poste local. Render n'exécute pas ce Compose. Le déploiement est décrit dans [deploiement-render.md](deploiement-render.md).

## 1. Installer Docker

1. Installez Docker Desktop pour Windows depuis la documentation officielle Docker.
2. Démarrez Docker Desktop et attendez qu'il indique que le moteur tourne.
3. Ouvrez PowerShell et vérifiez :

```powershell
docker version
docker compose version
```

Les deux commandes doivent afficher une version. Si PowerShell répond que `docker` n'est pas reconnu, Docker n'est pas installé ou son répertoire n'est pas dans le PATH. Aucune autre installation n'est nécessaire.

## 2. Préparer le fichier d'environnement

Placez-vous à la racine du dépôt, là où se trouvent `artisan` et `docker-compose.yml`.

```powershell
powershell -File scripts/prepare-env.ps1
```

Le script copie `.env.example` vers `.env` seulement si `.env` n'existe pas. Il ne remplace jamais un `.env` déjà présent. `.env` est ignoré par Git.

`.env.example` reste la référence des noms et des valeurs locales :

| Variable Laravel | Valeur de référence | Effet dans Compose |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Conservée |
| `DB_HOST` | `127.0.0.1` | Remplacée dans les conteneurs par le nom du service `mysql` |
| `DB_PORT` | `3306` | Port interne MySQL |
| `DB_DATABASE` | `crm_db` | Nom de la base |
| `DB_USERNAME` | `root` | Utilisateur Laravel |
| `DB_PASSWORD` | vide | Mot de passe Laravel |

L'image officielle MySQL n'accepte pas `MYSQL_USER=root`, et un mot de passe root vide exige `MYSQL_ALLOW_EMPTY_PASSWORD`. Le Compose local suit donc `.env.example` avec :

- `MYSQL_DATABASE` égal à `DB_DATABASE` ;
- `MYSQL_ALLOW_EMPTY_PASSWORD=yes` ;
- pas de `MYSQL_USER`, `MYSQL_PASSWORD` ni `MYSQL_ROOT_PASSWORD`.

Ces trois dernières variables sont réservées à la production, où le mot de passe vide est interdit. Elles sont décrites dans le guide Render.

`APP_KEY` est déjà présente dans `.env.example`. Elle sert au poste local. Ne la recopiez pas dans Render : la production doit avoir sa propre clé, créée une seule fois.

## 3. Construire et démarrer

```powershell
docker compose --env-file .env up -d --build
```

Cette commande :

1. lit `.env` pour l'interpolation Compose, notamment `DB_DATABASE` ;
2. transmet `.env` aux conteneurs `app` et `queue` ;
3. force `DB_HOST=mysql` pour ces conteneurs, ce qui l'emporte sur `127.0.0.1` ;
4. construit l'image de développement, cible `development` du Dockerfile, avec `composer install` sur le `composer.lock` existant ;
5. démarre MySQL 8.4, attend son healthcheck, puis démarre l'API ;
6. exécute `php artisan migrate --force` une fois MySQL joignable, parce que le Compose local définit `RUN_MIGRATIONS=true`.

Le premier démarrage télécharge les images et les paquets Composer. Il peut prendre plusieurs minutes.

Trois services existent :

| Service | Rôle | Accès depuis Windows |
| --- | --- | --- |
| `app` | Nginx et PHP-FPM, port interne 8080 | `http://127.0.0.1:8080` |
| `queue` | `php artisan queue:work`, pour les notifications mises en file | aucun port |
| `mysql` | MySQL 8.4 | `127.0.0.1:3306`, visible seulement sur la machine |

Le port 3306 n'est pas exposé sur le réseau local autre que cette machine. En production Render, MySQL n'a pas d'adresse publique.

## 4. Vérifier

```powershell
docker compose --env-file .env ps
docker compose --env-file .env logs app
docker compose --env-file .env logs mysql
```

Puis, dans un navigateur ou avec `curl` :

```powershell
curl.exe http://127.0.0.1:8080/up
curl.exe http://127.0.0.1:8080/api/v1/settings
```

`/up` répond lorsque PHP et Nginx répondent. Ce n'est pas une preuve que chaque endpoint métier fonctionne. `/api/v1/settings` interroge la base. Une réponse JSON, même avec une liste vide ou une erreur métier explicite, montre que Laravel joint MySQL. Une erreur `DATABASE_SCHEMA_NOT_READY` signifie que les migrations ne sont pas à jour.

Les routes enregistrées :

```powershell
powershell -File scripts/artisan.ps1 route:list
```

## 5. Commandes d'administration

Toutes s'exécutent dans les conteneurs. Le script Artisan lance la commande avec l'utilisateur `www-data`, afin que les fichiers de `storage` restent inscriptibles par PHP.

```powershell
powershell -File scripts/artisan.ps1 migrate:status
powershell -File scripts/artisan.ps1 db:seed
docker compose --env-file .env exec app composer install
docker compose --env-file .env exec mysql mysql -uroot crm_db
```

`db:seed` lance `DatabaseSeeder` : rôles et tables de référence. Il ne crée pas d'utilisateur, parce que `DatabaseSeeder` n'appelle pas `UserSeeder`.

`UserSeeder` crée des comptes de développement dont le mot de passe est `password`, et il contient des adresses personnelles. Ne l'exécutez pas sur une base de production. En local seulement, si vous acceptez ces données :

```powershell
powershell -File scripts/artisan.ps1 db:seed --class=Database\Seeders\UserSeeder
```

Les migrations locales sont automatiques au démarrage du service `app`. Le service `queue` ne les relance pas. Pour les appliquer à la main :

```powershell
powershell -File scripts/artisan.ps1 migrate --force
```

Ne lancez pas cette commande contre une base de production depuis ce Compose.

## 6. Données persistantes

Deux volumes Docker sont créés :

- `mysql_data`, monté sur `/var/lib/mysql` ;
- `app_storage`, monté sur `/var/www/html/storage`.

`docker compose stop` et `docker compose down` conservent ces volumes. Les données MySQL et les fichiers uploadés restent.

MySQL n'initialise les comptes et le nom de la base que lorsque le volume est vide. Modifier ensuite `DB_DATABASE`, `DB_USERNAME` ou `DB_PASSWORD` dans `.env` ne change pas une base déjà créée dans `mysql_data`. Pour repartir d'une base vide, il faut supprimer le volume, ce qui efface les données :

```powershell
docker compose --env-file .env down
docker volume rm crm-backend_mysql_data
```

Ne faites cela que sur la base locale, et seulement si la perte des données est acceptée. Reconstruire l'image ne vide pas le volume :

```powershell
docker compose --env-file .env up -d --build
```

## 7. Sauvegarde et restauration locales

La sauvegarde doit être faite avec `mysqldump`, pas en copiant le volume à chaud.

```powershell
docker compose --env-file .env exec mysql mysqldump -uroot --databases crm_db > sauvegarde.sql
```

Restauration dans la base locale :

```powershell
Get-Content sauvegarde.sql | docker compose --env-file .env exec -T mysql mysql -uroot
```

Vérifiez le nom du fichier avant de restaurer. Cette opération écrit dans la base du conteneur local.

## 8. Journaux, arrêt, reconstruction

```powershell
docker compose --env-file .env logs -f app
docker compose --env-file .env logs -f queue
docker compose --env-file .env logs -f mysql
docker compose --env-file .env stop
docker compose --env-file .env down
```

`down` sans `-v` garde les volumes. `down -v` les supprime.

Les e-mails locaux (`MAIL_MAILER=log`) apparaissent dans les journaux de `app` ou dans `storage/logs` du volume, pas dans une boîte mail.

## 9. Tests PHPUnit

```powershell
docker compose --env-file .env exec -u www-data app php artisan test
```

`phpunit.xml` force SQLite en mémoire. Ces tests ne se connectent pas au MySQL du Compose et ne modifient pas ses données. Plusieurs migrations contiennent du SQL MySQL : un échec de test peut venir de cette incompatibilité déjà présente. Il ne faut pas changer les migrations pour faire passer SQLite, et il ne faut pas pointer ces tests vers une base de production.

## 10. Problèmes fréquents

**`docker` n'est pas reconnu.** Docker Desktop n'est pas installé ou pas démarré.

**Le fichier `.env` est introuvable.** Relancez `scripts/prepare-env.ps1`. Compose refuse de démarrer sans lui, parce que `env_file` le désigne explicitement.

**L'API répond, mais la base échoue.** Consultez `docker compose logs mysql` et `docker compose ps`. Le healthcheck MySQL utilise `mysqladmin ping` avec l'utilisateur `root` sans mot de passe, conformément à `.env.example`.

**Les identifiants ont été changés et MySQL refuse la connexion.** Le volume a déjà été initialisé avec les anciens identifiants. Voir la section 6.

**Un upload semble réussi, mais l'URL du fichier ne s'ouvre pas.** Le lien `public/storage` est créé au démarrage. Le fichier est dans le volume `app_storage`. Vérifiez que le conteneur `app` a bien redémarré après le premier lancement.

**Les notifications internes ne partent pas.** Le service `queue` doit être en cours d'exécution. Les notifications concernées sont mises en file dans MySQL.

**Le port 8080 ou 3306 est déjà pris.** Arrêtez le logiciel qui l'occupe, ou changez uniquement la partie gauche du mapping dans `docker-compose.yml` (`127.0.0.1:8081:8080`). Ne changez pas le port interne 8080 sans changer aussi `PORT`.
