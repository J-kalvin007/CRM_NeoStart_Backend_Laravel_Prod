<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveWorkflowStep extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['acted_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->withoutGlobalScopes();
    }
}
