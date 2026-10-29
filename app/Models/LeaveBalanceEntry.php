<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveBalanceEntry extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['days' => 'float', 'remaining_after' => 'float'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->withoutGlobalScopes();
    }
}
