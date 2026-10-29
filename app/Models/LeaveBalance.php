<?php

namespace App\Models;

use App\Models\Traits\FiltersSelectedCountry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use FiltersSelectedCountry;
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['initial_days' => 'float', 'remaining_days' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entries()
    {
        return $this->hasMany(LeaveBalanceEntry::class);
    }
}
