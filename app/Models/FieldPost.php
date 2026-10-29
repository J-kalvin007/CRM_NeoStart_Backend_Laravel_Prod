<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FieldPost extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry, HasUuids;

    protected $fillable = ['country_id', 'name', 'city', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
