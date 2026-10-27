<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampleMovement extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = [
        'product_id',
        'user_id',
        'contact_id',
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

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}

