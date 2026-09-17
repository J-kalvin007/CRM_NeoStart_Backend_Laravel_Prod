<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachingSession extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'supervisor_id',
        'delegate_id',
        'strengths',
        'weaknesses',
        'action_plan',
        'next_review_date',
        'status',
    ];

    protected $casts = [
        'next_review_date' => 'date',
    ];

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }
}
