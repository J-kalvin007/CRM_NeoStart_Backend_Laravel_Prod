#!/usr/bin/env sh
# Run on the deployed backend, from any working directory. Does not seed or migrate data.
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan reports:check
