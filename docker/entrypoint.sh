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
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ "${APP_ENV:-local}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY est absente. Générez-la une seule fois et enregistrez-la dans Render. Elle ne doit pas être recréée à chaque démarrage." >&2
    exit 1
fi

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

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Exécution des migrations sur la base désignée par les variables DB_*."
    setpriv --reuid=www-data --regid=www-data --init-groups \
        -- php artisan migrate --force --no-interaction
fi

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

if [ "${1:-}" = "supervisord" ]; then
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
