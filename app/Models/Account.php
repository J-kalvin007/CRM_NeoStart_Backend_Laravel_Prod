<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Account extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'country_id', 'user_id', 'delegue_id', 'account_type_id',
        'nom', 'adresse', 'ville', 'quartier', 'zone_quartier',
        'responsable', 'responsable_prenom', 'responsable_nom',
        'gps_lat', 'gps_lng', 'telephone', 'whatsapp', 'email',
        'potential_id', 'score', 'potentiel_commande_mois',
        'grossiste_principal', 'produits_disponibles',
        'statut', 'statut_validation', 'rejection_reason', 'meta', 'local_uuid', 'code_externe',
    ];

    protected $casts = [
        'gps_lat'               => 'float',
        'gps_lng'               => 'float',
        'score'                 => 'integer',
        'meta'                  => 'array',
        'produits_disponibles'  => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function ($account) {
            $isPharmacie = false;
            if ($account->relationLoaded('accountType') && $account->accountType) {
                $code = strtolower($account->accountType->code ?? $account->accountType->libelle ?? '');
                if (str_contains($code, 'pha') || str_contains($code, 'off')) {
                    $isPharmacie = true;
                }
            } elseif ($account->account_type_id) {
                $type = AccountType::find($account->account_type_id);
                if ($type) {
                    $code = strtolower($type->code ?? $type->libelle ?? '');
                    if (str_contains($code, 'pha') || str_contains($code, 'off')) {
                        $isPharmacie = true;
                    }
                }
            }

            if ($isPharmacie && !empty($account->nom)) {
                $trimmed = trim($account->nom);
                if (!preg_match('/^PHARMACIE\b/i', $trimmed)) {
                    $account->nom = 'PHARMACIE ' . $trimmed;
                }
            }
        });
    }

    public function potential()    { return $this->belongsTo(Potential::class); }
    public function country()      { return $this->belongsTo(Country::class); }
    public function owner()        { return $this->belongsTo(User::class, 'user_id'); }
    public function user()         { return $this->belongsTo(User::class, 'user_id'); }
    public function delegue()      { return $this->belongsTo(User::class, 'delegue_id'); }
    public function accountType()  { return $this->belongsTo(AccountType::class); }
    public function contacts()     { return $this->hasMany(Contact::class); }
    public function categories()   { return $this->belongsToMany(Category::class, 'account_category'); }
    public function activities()   { return $this->hasMany(Activity::class); }
}

