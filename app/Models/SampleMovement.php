<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampleMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'user_id',
        'activity_id',
        'movement_type',
        'quantity',
        'lot_number',
        'expiry_date',
        'signature_url',
        'qr_code',
        'stock_apres',
        'remarques',
        'local_uuid',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'stock_apres' => 'integer',
        'expiry_date' => 'date',
    ];

    /* ─── Relations ──────────────────────────────────────────── */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
