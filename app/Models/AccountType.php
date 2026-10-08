<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasActivityLogs;

class AccountType extends Model
{
    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = ['code', 'libelle', 'description', 'couleur', 'icone', 'is_active', 'statut', 'ordre', 'validation_rules', 'is_account_only'];

    protected $casts = [
        'is_active' => 'boolean', 
        'is_account_only' => 'boolean',
        'ordre' => 'integer',
        'validation_rules' => 'array',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'account_type_country');
    }

    public function activityTypes()
    {
        return $this->belongsToMany(ActivityType::class, 'account_type_activity_type');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('statut', ['actif', 'valide'])->orWhere('is_active', true);
        });
    }
}
