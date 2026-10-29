<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComplianceFinding extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry, HasUuids;

    protected $guarded = [];

    protected $casts = ['evidence' => 'array', 'detected_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decisions()
    {
        return $this->hasMany(ComplianceDecision::class, 'finding_id');
    }
}
