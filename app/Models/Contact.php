<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'account_id', 'user_id', 'contact_type_id',
        'speciality_id', 'segmentation_id', 'potential_id', 'influence_id',
        'nom', 'prenom', 'titre',
        'telephone', 'whatsapp', 'email',
        'ville', 'zone_quartier', 'adresse',
        'gps_lat', 'gps_lng',
        'score',
        'nb_patients_cible_mois', 'nb_visites_recommandees_mois',
        'produit_prioritaire', 'produit_secondaire', 'autre_produit_interet',
        'canal_prefere', 'statut_relation',
        'statut', 'statut_validation', 'rejection_reason', 'meta', 'local_uuid', 'code_externe',
    ];

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'score'   => 'integer',
        'meta'    => 'array',   // JSON automatiquement décodé
                                // Pharma: {"rpps":"12345","is_kol":true,"nb_consultations":30}
    ];

    public function account()      { return $this->belongsTo(Account::class); }
    public function accounts()     { return $this->belongsToMany(Account::class, 'account_contact')->withPivot('is_primary')->withTimestamps(); }
    public function owner()        { return $this->belongsTo(User::class, 'user_id'); }
    public function user()         { return $this->belongsTo(User::class, 'user_id'); }
    public function delegue()      { return $this->belongsTo(User::class, 'user_id'); }
    public function contactType()  { return $this->belongsTo(ContactType::class); }
    public function speciality()   { return $this->belongsTo(Speciality::class); }
    public function segmentation() { return $this->belongsTo(Segmentation::class); }
    public function potential()    { return $this->belongsTo(Potential::class); }
    public function influence()    { return $this->belongsTo(Influence::class); }
    public function categories()   { return $this->belongsToMany(Category::class, 'contact_category'); }
    public function activities()   { return $this->hasMany(Activity::class); }
    public function participatingActivities() { return $this->belongsToMany(Activity::class, 'activity_contact', 'contact_id', 'activity_id')->withTimestamps(); }
    public function kolProfile()   { return $this->hasOne(KolProfile::class); }

    // Accesseur pratique pour récupérer un attribut meta (ex: $contact->getMeta('rpps'))
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }
}

