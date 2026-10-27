<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampleRequest extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'status',
        'approved_by',
        'approved_quantity',
        'rejection_reason',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
