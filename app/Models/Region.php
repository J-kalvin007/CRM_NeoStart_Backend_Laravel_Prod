<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Region extends Model
{
    use HasUuids;

    protected $fillable = [
        'subsidiary_id',
        'name',
        'manager_id',
    ];

    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class);
    }

    public function territories()
    {
        return $this->hasMany(Territory::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
