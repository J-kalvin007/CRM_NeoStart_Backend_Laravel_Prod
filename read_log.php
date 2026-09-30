<?php
$lines = file('storage/logs/laravel.log');
$start = max(0, count($lines) - 200);
for ($i = $start; $i < count($lines); $i++) {
    echo $lines[$i];
}
