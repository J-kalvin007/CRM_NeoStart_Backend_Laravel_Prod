<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasPerimeter;

class Opportunity extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'user_id',
        'account_id',
        'product_id',
        'title',
        'description',
        'expected_revenue',
        'probability',
        'status',
        'expected_close_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
