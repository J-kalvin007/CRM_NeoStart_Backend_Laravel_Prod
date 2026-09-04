<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Country extends Model
{
    use HasActivityLogs;
    use HasUuids;

    protected $fillable = [
        'name',
        'iso_code',
        'currency',
        'language',
        'timezone',
        'regulatory_settings',
        'status',
    ];

    protected $casts = [
        'regulatory_settings' => 'array',
        'status' => 'boolean',
    ];

    public function subsidiaries()
    {
        return $this->hasMany(Subsidiary::class);
    }
}
