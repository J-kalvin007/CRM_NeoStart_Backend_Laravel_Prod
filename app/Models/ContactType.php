<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasActivityLogs;

class ContactType extends Model
{
    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = ['code', 'libelle', 'description', 'couleur', 'icone', 'is_active', 'statut', 'ordre', 'validation_rules'];

    protected $casts = [
        'is_active' => 'boolean', 
        'ordre' => 'integer',
        'validation_rules' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('statut', ['actif', 'valide'])->orWhere('is_active', true);
    }
}
