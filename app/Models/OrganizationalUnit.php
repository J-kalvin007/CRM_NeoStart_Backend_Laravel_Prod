<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationalUnit extends Model
{
    use HasFactory, HasUuids, HasActivityLogs;

    protected  = [
        'name',
        'type',
        'parent_id',
        'manager_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_scopes');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
