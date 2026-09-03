<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Territory extends Model
{
    use HasUuids;

    protected $fillable = [
        'region_id',
        'name',
        'manager_id',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
