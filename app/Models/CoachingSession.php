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
        'activity_id',
        'type',
        'strengths',
        'weaknesses',
        'action_plan',
        'next_review_date',
        'status',
        'date_duo',
        'zone',
        'motif',
        'objectifs_prevus_json',
        'resultats_constates_json',
        'taux_atteinte_pct',
        'commentaires_superviseur',
        'plan_action_recommande',
        'local_uuid',
    ];

    protected $casts = [
        'next_review_date' => 'date',
        'date_duo' => 'date',
        'objectifs_prevus_json' => 'array',
        'resultats_constates_json' => 'array',
        'taux_atteinte_pct' => 'float',
    ];

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
