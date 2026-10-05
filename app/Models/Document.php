<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasPerimeter;

class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'country_id',
        'speciality_id',
        'role_id',
        'documentable_id',
        'documentable_type',
        'title',
        'description',
        'type',
        'file_path',
        'viewer_path',
        'version',
        'is_active',
        'status',
        'expiration_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expiration_date' => 'date',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }


    public function speciality()
    {
        return $this->belongsTo(Speciality::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
