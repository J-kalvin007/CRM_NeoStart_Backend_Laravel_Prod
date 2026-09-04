<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\HasPerimeter;

class Subsidiary extends Model
{
    use HasFactory, HasUuids, HasPerimeter;
    use HasActivityLogs;

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
