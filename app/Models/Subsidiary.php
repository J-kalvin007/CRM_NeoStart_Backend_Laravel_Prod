<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Subsidiary extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_id',
        'name',
        'code',
        'address',
        'manager_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function regions()
    {
        return $this->hasMany(Region::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
