<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class GalenicForm extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'subsidiary_id',
        'code',
        'nom',
        'description',
        'statut'
    ];

    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class);
    }
}
