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

    protected $fillable = ['code', 'libelle', 'description', 'couleur', 'icone', 'is_active', 'ordre', 'validation_rules'];

    protected $casts = [
        'is_active' => 'boolean', 
        'ordre' => 'integer',
        'validation_rules' => 'array',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
