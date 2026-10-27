<?php

namespace App\Models;

use App\Models\Traits\FiltersSelectedCountry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SampleQuota extends Model
{
    use HasUuids, FiltersSelectedCountry;

    protected $fillable = ['user_id', 'product_id', 'quota', 'period_start', 'period_end'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
