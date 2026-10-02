<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasActivityLogs;

class ObjectifType extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasActivityLogs;

    protected $table = 'objectif_types';

    protected $fillable = [
        'name',
        'description',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function objectifs()
    {
        return $this->hasMany(Objectif::class, 'objectif_type_id');
    }
}
