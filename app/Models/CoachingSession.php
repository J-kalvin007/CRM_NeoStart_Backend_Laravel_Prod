<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachingSession extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'supervisor_id',
        'delegate_id',
        'activity_id',
        'session_type',
        'evaluator_id',
        'evaluated_id',
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
        'note_globale',
        'note_sur_20',
        'respect_canevas_pct',
        'canevas_evaluation_json',
        'points_forts',
        'axes_amelioration',
        'commentaires_superviseur',
        'plan_action_recommande',
        'local_uuid',
    ];

    protected $casts = [
        'next_review_date' => 'date',
        'date_duo' => 'date',
        'objectifs_prevus_json' => 'array',
        'resultats_constates_json' => 'array',
        'canevas_evaluation_json' => 'array',
        'taux_atteinte_pct' => 'float',
        'note_globale' => 'float',
        'note_sur_20' => 'float',
        'respect_canevas_pct' => 'float',
    ];

    protected $appends = [
        'evaluator_name',
    ];

    public function getEvaluatorNameAttribute(): ?string
    {
        return $this->evaluator?->name ?? $this->supervisor?->name;
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id')->withoutGlobalScopes();
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id')->withoutGlobalScopes();
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id')->withoutGlobalScopes();
    }

    public function evaluated()
    {
        return $this->belongsTo(User::class, 'evaluated_id')->withoutGlobalScopes();
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
