<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasPerimeter;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'status',
        'location',
        'start_time',
        'end_time',
        'budget',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
