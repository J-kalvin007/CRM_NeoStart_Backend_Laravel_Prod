<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DelegateRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'contact_id',
        'request_type',
        'reason',
        'priority',
        'status',
        'local_uuid',
    ];
}
