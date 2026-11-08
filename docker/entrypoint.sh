#!/bin/sh
set -eu

cd /var/www/html

PORT="${PORT:-8080}"
export PORT

envsubst '${PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/private \
    storage/app/firebase \
    storage/api-docs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ "${APP_ENV:-local}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY est absente. Générez-la une seule fois et enregistrez-la dans Render. Elle ne doit pas être recréée à chaque démarrage." >&2
    exit 1
fi

# Serveur OpenAPI : aligner sur APP_URL si non fourni explicitement.
# Sans cela, L5-Swagger garde le host par défaut (http://my-default-host.com).
if [ -z "${L5_SWAGGER_CONST_HOST:-}" ] && [ -n "${APP_URL:-}" ]; then
    export L5_SWAGGER_CONST_HOST="$APP_URL"
fi

# Attente active de MySQL avant migrations / démarrage applicatif.
# Obligatoire en production Docker (Render) : le web peut démarrer avant
# que le service privé MySQL soit prêt. Sans cette boucle, migrate échoue.
if [ "${WAIT_FOR_DB:-false}" = "true" ]; then
    echo "Attente de MySQL (${DB_HOST:-inconnu}:${DB_PORT:-3306}, base ${DB_DATABASE:-inconnue})..."
    attempt=0
    until php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE") ?: "";
        $username = getenv("DB_USERNAME") ?: "root";
        $password = getenv("DB_PASSWORD");
        if ($password === false) {
            $password = "";
        }
        try {
            new PDO(
                "mysql:host={$host};port={$port};dbname={$database}",
                $username,
                $password
            );
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    '; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 60 ]; then
            echo "MySQL est resté inaccessible après 60 tentatives." >&2
            exit 1
        fi
        sleep 2
    done
    echo "MySQL répond."
fi

# Application du schéma Laravel sur la base pointée par DB_*.
# --force : autorise migrate hors mode interactif (requis en production).
# Idempotent : seules les migrations absentes de la table `migrations`
# sont exécutées. Un redémarrage sans nouvelle migration ne change rien.
# Activer uniquement sur le service web (pas sur le worker queue).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Exécution des migrations sur la base désignée par les variables DB_*."
    setpriv --reuid=www-data --regid=www-data --init-groups \
        -- php artisan migrate --force --no-interaction
fi

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

# Documentation OpenAPI (L5-Swagger) :
# Sur Render/Compose, un volume est monté sur /var/www/html/storage. Il masque
# le fichier api-docs.json éventuellement présent dans l'image. Sans régénération
# au démarrage, /api/documentation reste une page blanche.
# Uniquement pour le service web (supervisord), pas pour le worker queue.
if [ "${RUN_SWAGGER_GENERATE:-true}" = "true" ] && [ "${1:-}" = "supervisord" ]; then
    echo "Génération de la documentation OpenAPI (storage/api-docs/api-docs.json)..."
    setpriv --reuid=www-data --regid=www-data --init-groups \
        -- php artisan l5-swagger:generate --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

if [ "${1:-}" = "supervisord" ]; then
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
