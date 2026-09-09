<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'subsidiary_id', 'product_type_id',
        'nom', 'code', 'marque', 'gamme',
        'prix_unitaire', 'pght', 'ppv', 'devise',
        'stock_actuel', 'stock_alerte',
        'description', 'image_url', 'statut',
        'meta',
    ];

    protected $casts = [
        'prix_unitaire' => 'float',
        'pght'          => 'float',
        'ppv'           => 'float',
        'stock_actuel'  => 'integer',
        'stock_alerte'  => 'integer',
        'meta'          => 'array',   // JSON automatiquement décodé
                                      // Pharma: {"molecule":"Amoxicilline","dosage":"500mg","forme":"Comprimé","amm":"AMM-001"}
                                      // B2B:    {"ref_fournisseur":"REF-XYZ","poids_kg":2.5}
    ];

    public function subsidiary()  { return $this->belongsTo(Subsidiary::class); }
    public function productType() { return $this->belongsTo(ProductType::class); }
    public function activities()  {
        return $this->belongsToMany(Activity::class, 'activity_product')
            ->withPivot('quantity', 'customer_interest', 'remarques')
            ->withTimestamps();
    }
    public function sampleMovements() { return $this->hasMany(SampleMovement::class); }
    public function objectifs()       { return $this->hasMany(Objectif::class); }

    public function scopeActif($query)        { return $query->where('statut', 'actif'); }
    public function scopeEnStock($query)      { return $query->where('stock_actuel', '>', 0); }
    public function scopeStockAlerte($query)  { return $query->whereColumn('stock_actuel', '<=', 'stock_alerte'); }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }
}

