<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\HasPerimeter;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory, HasUuids, HasPerimeter;

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
