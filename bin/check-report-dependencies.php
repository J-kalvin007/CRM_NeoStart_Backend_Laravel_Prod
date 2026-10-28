<?php
// Also run after composer install: missing vendor classes must fail deployment, not a user's download.
$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR, "Dépendances absentes : exécutez composer install.\n"); exit(1); }
require $autoload;
$dependencies = new \App\Support\ReportDependencies;
$missing = array_unique([...$dependencies->missing('pdf'), ...$dependencies->missing('xlsx')]);
if ($missing) {
    fwrite(STDERR, "Exports indisponibles : ".implode(', ', $missing)."\nExécutez composer install --no-dev --prefer-dist --optimize-autoloader dans CRM-backend.\n");
    exit(1);
}
fwrite(STDOUT, "Moteurs PDF et Excel : disponibles.\n");
