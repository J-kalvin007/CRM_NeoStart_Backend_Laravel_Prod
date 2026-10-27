<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PharmacovigilanceReport extends Model
{
    use \App\Models\Traits\FiltersSelectedCountry;

    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'contact_id',
        'product_id',
        'patient_initials',
        'patient_age',
        'patient_gender',
        'adverse_event_description',
        'severity',
        'report_date',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
