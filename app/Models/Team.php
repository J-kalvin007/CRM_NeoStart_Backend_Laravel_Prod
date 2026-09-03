<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Team extends Model
{
    use HasUuids;

    protected $fillable = [
        'territory_id',
        'name',
        'manager_id',
    ];

    public function territory()
    {
        return $this->belongsTo(Territory::class);
    }

    public function members()
    {
        return $this->hasMany(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
