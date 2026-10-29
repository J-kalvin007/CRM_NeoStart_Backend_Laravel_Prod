<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveDocument extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [];

    protected $hidden = ['path'];

    public function request()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
