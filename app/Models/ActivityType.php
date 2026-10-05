<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\HasActivityLogs;

class ActivityType extends Model
{
    use HasUuids, HasActivityLogs;

    protected $fillable = ['name', 'code', 'description', 'is_active', 'is_multi'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_multi' => 'boolean',
    ];

    public function accountTypes()
    {
        return $this->belongsToMany(AccountType::class, 'account_type_activity_type');
    }
}
