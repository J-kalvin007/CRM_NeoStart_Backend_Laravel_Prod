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
        'product_type_id', 'laboratory_id', 'therapeutic_class_id', 'galenic_form_id',
        'nom', 'code', 'marque', 'gamme',
        'description', 'image_url', 'statut',
        'dci', 'conditionnement', 'indications', 'posologie', 'effets_secondaires',
        'meta',
    ];

    protected $casts = [
        'meta'          => 'array',
    ];

    public function countries() { 
        return $this->belongsToMany(Country::class)
            ->withPivot('pght', 'prix_cession', 'ppv', 'devise', 'stock_actuel', 'stock_alerte')
            ->withTimestamps(); 
    }
    public function productType() { return $this->belongsTo(ProductType::class); }
    public function laboratory()  { return $this->belongsTo(Laboratory::class); }
    public function therapeuticClass() { return $this->belongsTo(TherapeuticClass::class); }
    public function galenicForm() { return $this->belongsTo(GalenicForm::class); }
    public function activities()  {
        return $this->belongsToMany(Activity::class, 'activity_product')
            ->withPivot('quantity', 'customer_interest', 'remarques')
            ->withTimestamps();
    }
    public function sampleMovements() { return $this->hasMany(SampleMovement::class); }
    public function objectifs()       { return $this->hasMany(Objectif::class); }
    public function objections()      { return $this->hasMany(Objection::class); }
    


    public function scopeActif($query)        { return $query->where('statut', 'actif'); }
    public function scopeEnStock($query)      { return $query->where('stock_actuel', '>', 0); }
    public function scopeStockAlerte($query)  { return $query->whereColumn('stock_actuel', '<=', 'stock_alerte'); }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }
}

