<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasActivityLogs;

class Campaign extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids, SoftDeletes, HasActivityLogs;

    protected $fillable = [
        'country_id',
        'nom',
        'date_debut',
        'date_fin',
        'cible',
        'message',
        'budget',
        'objectifs',
        'meta',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'budget' => 'decimal:2',
        'objectifs' => 'array',
        'meta' => 'array',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'campaign_product');
    }


    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
