<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'account_id', 'user_id', 'contact_type_id',
        'nom', 'prenom', 'titre',
        'telephone', 'email', 'gps_lat', 'gps_lng',
        'potentiel', 'influence', 'score',
        'statut', 'meta', 'local_uuid',
    ];

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'score'   => 'integer',
        'meta'    => 'array',   // JSON automatiquement décodé
                                // Pharma: {"rpps":"12345","is_kol":true,"nb_consultations":30}
    ];

    public function account()      { return $this->belongsTo(Account::class); }
    public function owner()        { return $this->belongsTo(User::class, 'user_id'); }
    public function contactType()  { return $this->belongsTo(ContactType::class); }
    public function categories()   { return $this->belongsToMany(Category::class, 'contact_category'); }
    public function activities()   { return $this->hasMany(Activity::class); }

    // Accesseur pratique pour récupérer un attribut meta (ex: $contact->getMeta('rpps'))
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }
}
