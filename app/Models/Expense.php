<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasPerimeter;

class Expense extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'amount',
        'currency',
        'expense_date',
        'expensable_id',
        'expensable_type',
        'status',
        'approved_by',
        'rejection_reason',
        'receipt_file_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function expensable()
    {
        return $this->morphTo();
    }
}
