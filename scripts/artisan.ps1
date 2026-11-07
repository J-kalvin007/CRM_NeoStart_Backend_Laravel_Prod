$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $root
docker compose --env-file .env exec -u www-data app php artisan @args
