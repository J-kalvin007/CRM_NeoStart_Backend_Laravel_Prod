<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasActivityLogs;

class KolProfile extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected $fillable = [
        'contact_id',
        'expertise',
        'influence_institution',
        'publications',
        'relations_historique',
        'engagement',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
