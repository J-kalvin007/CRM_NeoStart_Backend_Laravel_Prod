# Déploiement sur Render

Render construit l'image Docker de l'application. Il ne lance pas `docker-compose.yml`. Compose reste l'environnement local. La production est décrite par `render.yaml` et par le tableau de bord Render.

Sources utilisées : documentation Render « Docker on Render », « Web Services », « Deploy MySQL », « Persistent Disks », « Health Checks » et « Blueprint YAML Reference ». Render ne propose pas de base MySQL managée. Sa base managée est PostgreSQL. Elle n'est pas utilisée. MySQL reste un service privé Docker, avec un disque persistant, comme le décrit le guide officiel « Deploy MySQL ».

## Architecture de production

Trois services, dans la même région Render. Le réseau privé ne traverse pas les régions.

| Service | Type | Image | Rôle |
| --- | --- | --- | --- |
| `crm-backend` | Web service Docker | Cible `production` du Dockerfile, car c'est le dernier stage | API HTTPS publique. Nginx écoute `0.0.0.0` sur la variable `PORT` injectée par Render. La valeur par défaut de Render est `10000`. |
| `crm-queue` | Background worker Docker | Même Dockerfile | Exécute `php artisan queue:work` pour les notifications déjà mises en file par l'application. |
| `crm-mysql` | Private service, image `mysql:8.4` | Image officielle | MySQL, joignable seulement par les autres services du même compte et de la même région, sur le port `3306`. |

Le service web a un disque monté sur `/var/www/html/storage` pour les fichiers uploadés. MySQL a un disque monté sur `/var/lib/mysql`. Ce chemin est obligatoire pour l'image officielle : un autre chemin laisse les données sur le disque éphémère.

Conséquences des disques, d'après Render :

- un disque n'est accessible que par un seul service et une seule instance ;
- le déploiement sans interruption est désactivé pour le service qui porte le disque ;
- une restauration de l'instantané du disque MySQL peut corrompre la base. Les sauvegardes fiables passent par `mysqldump` ;
- Render conserve des instantanés quotidiens du disque pendant sept jours, mais ils ne remplacent pas `mysqldump`.

Le worker n'a pas de disque. Les fichiers uploadés sont écrits par le service web. Les notifications en file n'ont pas besoin de ce disque.

## Ce que le conteneur web fait au démarrage

Le script `docker/entrypoint.sh` :

1. fait écouter Nginx sur `$PORT` ;
2. prépare les répertoires de `storage` ;
3. refuse de démarrer en production si `APP_KEY` est vide ;
4. attend MySQL si `WAIT_FOR_DB=true` ;
5. n'exécute les migrations que si `RUN_MIGRATIONS=true`.

Dans `render.yaml`, `RUN_MIGRATIONS` vaut `false`. Un redémarrage ne rejoue donc pas les migrations. `php artisan key:generate` n'est jamais appelé au démarrage.

La sonde HTTP Render est `GET /up`, déjà déclarée par Laravel dans `bootstrap/app.php`. Render attend un code 2xx ou 3xx en moins de cinq secondes. Cette sonde confirme que le processus HTTP répond. Elle ne vérifie pas une requête métier MySQL.

## Secrets et variables

Ne commitez aucune valeur de production. `render.yaml` marque les secrets avec `sync: false` : Render les demande à la création et ne les écrit pas dans le dépôt.

### Clé Laravel

Générez-la une seule fois, après que le Compose local fonctionne :

```powershell
powershell -File scripts/artisan.ps1 key:generate --show
```

Copiez la ligne affichée, de la forme `base64:...`, dans la variable `APP_KEY` du service web Render. Ne la régénérez pas à chaque déploiement : les données chiffrées par Laravel deviendraient illisibles. Le worker reprend la même valeur depuis le service web. N'utilisez pas la clé déjà présente dans `.env.example`.

### MySQL de production

À la création du service privé, choisissez des valeurs qui ne sont pas celles du poste local :

| Variable de l'image MySQL | Contrainte | Variable Laravel correspondante |
| --- | --- | --- |
| `MYSQL_DATABASE` | `crm_db`, déjà fixé dans le blueprint pour rester aligné avec `.env.example` | `DB_DATABASE` |
| `MYSQL_USER` | Utilisateur applicatif. Ne mettez pas `root` : l'image officielle le refuse. | `DB_USERNAME` |
| `MYSQL_PASSWORD` | Mot de passe de cet utilisateur | `DB_PASSWORD` |
| `MYSQL_ROOT_PASSWORD` | Mot de passe administrateur, distinct du précédent | aucune variable Laravel |

`DB_HOST` est le nom d'hôte privé du service `crm-mysql`. `DB_PORT` vaut `3306`. `DB_CONNECTION` vaut `mysql`.

Le mot de passe vide du fichier `.env.example` ne doit pas être repris en production. L'image MySQL n'appliquera ces comptes que lors du premier démarrage, sur un disque vide. Les changer ensuite dans le tableau de bord ne modifie pas les comptes déjà créés dans `/var/lib/mysql`.

### URL et e-mail

- `APP_URL` : l'URL HTTPS affichée par Render pour le service web, par exemple `https://crm-backend.onrender.com`. Le nom exact peut recevoir un suffixe. Copiez-le depuis le tableau de bord. Flutter utilisera cette origine.
- `FRONTEND_URL` : laissez vide si aucun site web ne consomme l'API.
- `MAIL_MAILER` est `log` dans le blueprint, comme dans `.env.example`. Les codes OTP et les e-mails de mot de passe sont alors écrits dans les journaux Render, pas envoyés. Pour qu'un téléphone reçoive ces messages, remplacez `MAIL_MAILER` par `smtp` et renseignez `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et `MAIL_SCHEME` dans le tableau de bord, avec les valeurs de votre fournisseur. Faites la même chose sur le worker si les notifications e-mail passent par la file.

### Firebase

`FIREBASE_CREDENTIALS` doit pointer vers le fichier JSON du compte de service, s'il est fourni par l'équipe qui exploite Firebase. Ne le commitez pas. Render permet d'ajouter un secret file : placez-le et mettez son chemin dans cette variable. Sans ce fichier, l'API HTTP fonctionne, mais les notifications push ne peuvent pas être envoyées.

### Autres variables de `.env.example`

Le blueprint reprend les pilotes déjà choisis : session, cache et file en base, disque local, broadcast en journal. Les variables AWS restent vides tant qu'aucun bucket n'est utilisé. `PV_ALERT_EMAIL` est demandée dans le tableau de bord ; laissez-la vide si vous n'avez pas encore d'adresse de pharmacovigilance.

`LOG_CHANNEL=stderr` en production envoie les journaux Laravel vers les journaux Render. Ce canal existe déjà dans `config/logging.php`. Le code n'a pas été modifié.

## Créer les services

1. Poussez le dépôt, y compris `Dockerfile`, `.dockerignore` et `render.yaml`, vers le dépôt Git que Render peut lire. Ne poussez pas `.env`.
2. Dans Render, créez un Blueprint à partir de ce dépôt, ou créez les trois services à la main avec les mêmes réglages.
3. Choisissez une région et gardez les trois services dans cette région.
4. Langage : Docker pour le web et le worker. Pour MySQL : image `docker.io/library/mysql:8.4`.
5. Renseignez les secrets demandés.
6. Le disque MySQL doit être monté sur `/var/lib/mysql`. Le disque du service web doit être monté sur `/var/www/html/storage`.
7. Health Check Path du service web : `/up`.
8. Ne définissez pas de Docker Command sur le service web : le Dockerfile démarre déjà Nginx et PHP-FPM. Le worker a la commande `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`. Si Render la transmet comme un seul nom de programme et que le worker s'arrête aussitôt, remplacez-la par `/bin/sh -c "exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600"`. L'entrypoint exécute cette commande après l'attente de MySQL, avec l'utilisateur du serveur web.
9. Lancez le premier déploiement. MySQL doit être créé avant que le web puisse passer son attente de base. Si le web échoue parce que MySQL n'est pas encore prêt, relancez son déploiement une fois MySQL healthy. L'entrypoint réessaie pendant environ deux minutes.

Le plan gratuit ne convient pas à un disque persistant. Le service MySQL et le service web qui portent un disque doivent être sur un plan payant qui autorise les disques.

## Migrations

Les fichiers de `database/migrations` créent et font évoluer tout le schéma : utilisateurs, jetons, cache, files, sessions, puis le métier CRM. Certaines étapes tardives modifient des colonnes avec du SQL MySQL et transforment des données déjà présentes. Les appliquer sur une base qui contient déjà des données peut donc changer ces données. Sur une base vide, elles construisent le schéma attendu par cette version du code.

Ne les lancez pas automatiquement à chaque démarrage. Quand vous décidez de les appliquer, faites-le une fois, après une sauvegarde :

1. Ouvrez le shell du service web dans Render, ou un one-off job.
2. Exécutez `php artisan migrate:status` et lisez les migrations en attente.
3. Sauvegardez MySQL avec `mysqldump` depuis le shell du service MySQL.
4. Exécutez `php artisan migrate --force` dans le service web.

Render propose aussi une pre-deploy command. Vous pouvez y mettre `php artisan migrate --force` le jour où vous choisissez que chaque déploiement applique les migrations en attente. Ce dépôt ne l'active pas, parce qu'une migration ne doit pas partir seulement parce qu'un conteneur redémarre. La pre-deploy command voit le réseau privé, donc MySQL, mais elle ne voit pas le disque du service web. Cela n'empêche pas les migrations : elles écrivent dans MySQL, pas sur ce disque.

N'exécutez pas `migrate:fresh` ni `db:wipe` en production.

## Vérifier le déploiement

1. Les journaux du service web montrent que Nginx écoute et que l'attente MySQL se termine.
2. `https://<votre-service>.onrender.com/up` répond avec succès.
3. `https://<votre-service>.onrender.com/api/v1/settings` répond en JSON. Une erreur de schéma indique que les migrations n'ont pas été appliquées. Une erreur de connexion indique un `DB_HOST`, un utilisateur ou un mot de passe incorrect.
4. `POST https://<votre-service>.onrender.com/api/v1/login` avec un utilisateur réel répond selon le contrat décrit dans [api-flutter.md](api-flutter.md). Sans utilisateur en base, la réponse d'identifiants incorrects est normale.
5. Le worker doit être démarré pour vider la table `jobs`.

## Sauvegarde de production

Depuis le shell du service privé MySQL, avec les variables de ce service :

```shell
mysqldump -h 127.0.0.1 -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --databases "$MYSQL_DATABASE" > /tmp/crm.sql
```

Copiez ce fichier hors du conteneur. Le disque éphémère `/tmp` ne le conserve pas. Ne restaurez pas un instantané de disque Render à la place de ce fichier. Pour restaurer, réinjectez le SQL avec le client `mysql` dans une fenêtre de maintenance, après avoir arrêté le service web et le worker afin qu'ils n'écrivent pas pendant l'opération.

## Point non modifié dans le code

Render termine TLS et transmet `X-Forwarded-Proto`. `bootstrap/app.php` ne déclare pas de proxies de confiance. Les URL absolues construites pendant une requête, notamment l'URL renvoyée par l'upload de média, peuvent donc commencer par `http://` au lieu de `https://`. Aucune modification n'a été faite, parce que cela toucherait le démarrage de l'application. Si Flutter reçoit une URL de fichier en HTTP, la correction minimale, à décider séparément, serait d'ajouter dans `bootstrap/app.php`, dans le callback `withMiddleware` déjà présent :

```php
$middleware->trustProxies(at: '*');
```

Cette ligne n'est pas appliquée dans ce dépôt.
