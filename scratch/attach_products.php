<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Activity;
use App\Models\Product;
use Illuminate\Support\Str;

$productIds = Product::pluck('id')->toArray();

if (empty($productIds)) {
    echo "No products found in DB.\n";
    exit;
}

$activities = Activity::all();

foreach ($activities as $activity) {
    // Attach 1 or 2 random products
    $count = rand(1, min(2, count($productIds)));
    $randomKeys = (array) array_rand($productIds, $count);
    
    $syncData = [];
    foreach ($randomKeys as $key) {
        $productId = $productIds[$key];
        $syncData[$productId] = [
            'id' => (string) Str::uuid(),
            'quantity' => rand(1, 5),
            'customer_interest' => collect(['faible', 'moyen', 'fort'])->random(),
            'remarques' => 'Échantillons distribués',
        ];
    }
    
    $activity->products()->sync($syncData);
}

echo "Successfully attached products to " . $activities->count() . " activities.\n";
