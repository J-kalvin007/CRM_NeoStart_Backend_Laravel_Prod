<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'zone_id',
        'organizational_unit_id',
        'name',
        'code',
        'is_active',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
