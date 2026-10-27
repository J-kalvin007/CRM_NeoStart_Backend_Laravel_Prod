<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClmPresentationAnalytic extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids;

    protected $fillable = [
        'activity_id',
        'document_id',
        'user_id',
        'contact_id',
        'total_duration_seconds',
        'slides_data',
        'completed',
        'presented_at',
    ];

    protected $casts = [
        'slides_data' => 'array',
        'completed' => 'boolean',
        'total_duration_seconds' => 'integer',
        'presented_at' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
