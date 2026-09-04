<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Account extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $fillable = [
        'subsidiary_id', 'user_id', 'account_type_id',
        'nom', 'adresse', 'ville', 'quartier',
        'gps_lat', 'gps_lng', 'telephone', 'email', 'responsable',
        'potentiel', 'score', 'statut', 'meta', 'local_uuid',
    ];

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'score'   => 'integer',
        'meta'    => 'array',   // JSON automatiquement décodé
    ];

    public function subsidiary()   { return $this->belongsTo(Subsidiary::class); }
    public function owner()        { return $this->belongsTo(User::class, 'user_id'); }
    public function accountType()  { return $this->belongsTo(AccountType::class); }
    public function contacts()     { return $this->hasMany(Contact::class); }
    public function categories()   { return $this->belongsToMany(Category::class, 'account_category'); }
    public function activities()   { return $this->hasMany(Activity::class); }
}

