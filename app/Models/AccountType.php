<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['code', 'libelle', 'description', 'couleur', 'icone', 'is_active', 'ordre'];

    protected $casts = ['is_active' => 'boolean', 'ordre' => 'integer'];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('ordre');
    }
}
