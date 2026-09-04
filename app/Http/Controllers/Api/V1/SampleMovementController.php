<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Product;

class SampleMovementController extends Controller {
    public function index() { return response()->json(["data" => []]); }
    public function myStock() {
        $products = Product::all()->map(function($p) {
            return [
                "id" => (string)$p->id,
                "product_name" => $p->nom,
                "batch_number" => "LOT-2026",
                "quantity_available" => $p->stock_actuel,
                "alert_threshold" => 20,
                "expiry_date" => "2027-12-31"
            ];
        });
        return response()->json(["data" => $products]);
    }
}
