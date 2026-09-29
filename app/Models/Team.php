<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = [
        'name',
        'sector_id',
        'manager_id',
    ];

    // public function members()
    // {
    //     return $this->hasMany(User::class);
    // }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
