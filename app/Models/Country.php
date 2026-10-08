<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Country extends Model
{
    use HasActivityLogs;
    use HasUuids;

    protected $fillable = [
        'name',
        'iso_code',
        'currency',
        'language',
        'timezone',
        'regulatory_settings',
        'status',
    ];

    protected $casts = [
        'regulatory_settings' => 'array',
        'status' => 'boolean',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function accountTypes()
    {
        return $this->belongsToMany(AccountType::class, 'account_type_country');
    }
}
