<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapeuticClass extends Model
{
    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = [
        'nom', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
