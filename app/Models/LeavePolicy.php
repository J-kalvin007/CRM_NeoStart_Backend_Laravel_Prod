<?php

namespace App\Models;

use App\Models\Traits\FiltersSelectedCountry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use FiltersSelectedCountry;
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['permission_rules' => 'array', 'absence_codes' => 'array'];
}
