<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'territory_id',
        'organizational_unit_id',
        'name',
        'code',
        'is_active',
    ];

    public function territory()
    {
        return $this->belongsTo(Territory::class);
    }

    public function sectors()
    {
        return $this->hasMany(Sector::class);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
