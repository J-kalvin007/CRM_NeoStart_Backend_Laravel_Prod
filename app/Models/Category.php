<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'nom',
        'slug',
        'description',
        'type',
        'couleur',
        'icone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /* ─── Relations ──────────────────────────────────────────── */

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_category');
    }

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_category');
    }
}
