<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomFieldValue extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'custom_field_id',
        'entity_id',
        'entity_type',
        'value',
    ];

    public function customField()
    {
        return $this->belongsTo(CustomField::class);
    }

    public function entity()
    {
        return $this->morphTo();
    }
}
