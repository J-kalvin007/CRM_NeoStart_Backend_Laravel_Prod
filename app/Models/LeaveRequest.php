<?php

namespace App\Models;

use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\Traits\FiltersSelectedCountry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use FiltersSelectedCountry;
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['return_date' => 'date', 'submitted_at' => 'datetime', 'debited_at' => 'datetime', 'days' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(DynamicPerimeterScope::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class)->withoutGlobalScope(DynamicPerimeterScope::class);
    }

    public function steps()
    {
        return $this->hasMany(LeaveWorkflowStep::class)->orderBy('step');
    }

    public function documents()
    {
        return $this->hasMany(LeaveDocument::class);
    }

    public function interim()
    {
        return $this->belongsTo(User::class, 'interim_user_id')->withoutGlobalScope(DynamicPerimeterScope::class);
    }
}
