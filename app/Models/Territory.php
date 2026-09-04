<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\HasPerimeter;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Territory extends Model
{
    use HasFactory, HasUuids, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'region_id',
        'name',
        'manager_id',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
