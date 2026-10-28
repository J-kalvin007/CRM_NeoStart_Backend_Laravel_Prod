<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'duration_minutes', 'type', 'status', 'category', 'file_url'];
    protected $casts = ['progress' => 'float', 'duration_minutes' => 'integer'];

    public function scopeForLearner(Builder $query, string $userId): Builder
    {
        return $query->leftJoin('training_user_progress as learner_progress', fn ($join) => $join
            ->on('learner_progress.training_id', '=', 'trainings.id')->where('learner_progress.user_id', $userId))
            ->select('trainings.*', 'trainings.status as catalogue_status')
            ->selectRaw("COALESCE(learner_progress.progress, 0) as progress, COALESCE(learner_progress.status, 'A_FAIRE') as status");
    }
}
