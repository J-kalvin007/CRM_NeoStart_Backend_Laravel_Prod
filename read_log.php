<?php
$lines = file('storage/logs/laravel.log');
$lastError = "";
foreach (array_reverse($lines) as $line) {
    if (strpos($line, '[2026-10-08') === 0) {
        $lastError = $line . $lastError;
        break;
    } else {
        $lastError = $line . $lastError;
    }
}
echo substr($lastError, 0, 1000);
