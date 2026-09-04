<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code', 'libelle', 'description',
        'is_stockable', 'is_distributable',
        'couleur', 'icone', 'is_active', 'ordre',
    ];

    protected $casts = [
        'is_stockable'     => 'boolean',
        'is_distributable' => 'boolean',
        'is_active'        => 'boolean',
        'ordre'            => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('ordre');
    }

    public function scopeDistributable($query)
    {
        return $query->where('is_distributable', true);
    }
}
