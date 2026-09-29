<?php
use App\Models\Product;
use App\Models\OrganizationalUnit;

$togo = OrganizationalUnit::where('name', 'like', '%Togo%')->first();
$benin = OrganizationalUnit::where('name', 'like', '%Bénin%')->orWhere('name', 'like', '%Benin%')->first();

if (!$togo || !$benin) {
    echo "Missing Togo or Benin perimeters.\n";
    return;
}

$products = Product::withoutGlobalScopes()->get();

foreach ($products as $product) {
    // Attach to Togo and Benin so they are visible initially for tests
    $product->organizationalUnits()->syncWithoutDetaching([$togo->id, $benin->id]);
}

echo "Attached " . $products->count() . " products to Togo and Benin.\n";
