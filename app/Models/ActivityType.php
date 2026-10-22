<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\HasActivityLogs;

class ActivityType extends Model
{
    use HasUuids, HasActivityLogs;

    protected $appends = ['planning_kind'];

    public function getPlanningKindAttribute(): string
    {
        return \App\Services\PlanningOptions::kind($this->code);
    }

    protected $fillable = ['name', 'code', 'description', 'is_active', 'is_multi', 'is_account_only'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_multi' => 'boolean',
        'is_account_only' => 'boolean',
    ];

    public function accountTypes()
    {
        return $this->belongsToMany(AccountType::class, 'account_type_activity_type');
    }
}
