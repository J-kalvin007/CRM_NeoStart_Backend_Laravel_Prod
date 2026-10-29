<?php

namespace App\Models;

use App\Models\Traits\FiltersSelectedCountry;
use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Campaign extends Model
{
    use FiltersSelectedCountry;
    use HasActivityLogs, HasFactory, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('authorized_country', function (Builder $query) {
            $viewer = Auth::user();
            if ($viewer && ! $viewer->isAdminOrDirection()) {
                $query->where('campaigns.country_id', $viewer->country_id ?? '__unassigned__');
            }
        });
    }

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
