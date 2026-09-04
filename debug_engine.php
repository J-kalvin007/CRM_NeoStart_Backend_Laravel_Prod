<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ind = App\Models\Indicator::latest()->first();
echo "Indicator: " . json_encode($ind) . "\n";

$objectif = App\Models\Objectif::latest()->first();
echo "Objectif: " . json_encode($objectif) . "\n";

$activity = App\Models\Activity::latest()->first();
echo "Activity: " . json_encode($activity) . "\n";

echo "Testing calculation engine:\n";
$engine = new App\Services\IndicatorCalculationEngine();
$engine->updateObjectivesForModel($activity);

$objectif->refresh();
echo "Objectif after recalculation: " . json_encode($objectif) . "\n";
