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
        'subsidiary_id',
        'documentable_id',
        'documentable_type',
        'title',
        'description',
        'type',
        'file_path',
        'version',
        'is_active',
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
}
