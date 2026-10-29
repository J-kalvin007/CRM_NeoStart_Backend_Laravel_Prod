<?php

namespace App\Models;

use App\Models\Scopes\DynamicPerimeterScope;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\HasActivityLogs;
use App\Models\Traits\HasPerimeter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasActivityLogs;
    use HasApiTokens, HasFactory, HasPerimeter, HasRoles, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',

        'manager_id',
        'country_id', 'field_post_id',
        'status',
        'fcm_token',
        'last_login_at',
        'force_password_change',
        'otp_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function fieldPost()
    {
        return $this->belongsTo(FieldPost::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'last_login_at' => 'datetime',
            'last_location_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'force_password_change' => 'boolean',
            'otp_enabled' => 'boolean',
        ];
    }

    public function locations()
    {
        return $this->hasMany(UserLocation::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Supérieur direct. Le lien hiérarchique est structurel : il ne dépend pas du périmètre
     * de visibilité de l'utilisateur courant (sinon un délégué ne « voit » pas son superviseur
     * et celui-ci n'est pas notifié de ses soumissions).
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id')
            ->withoutGlobalScope(DynamicPerimeterScope::class);
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id')
            ->withoutGlobalScope(DynamicPerimeterScope::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function accounts()
    {
        return $this->hasMany(Account::class, 'user_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_user');
    }

    public function sampleStocks()
    {
        return $this->hasMany(SampleStock::class, 'user_id');
    }

    /**
     * IDs des collaborateurs encadrés (récursif via manager_id) :
     * un superviseur obtient ses délégués, un directeur ses superviseurs et leurs délégués.
     */
    public function teamMemberIds(): array
    {
        $ids = [];
        $current = [$this->id];

        while (! empty($current)) {
            $children = static::withoutGlobalScopes()
                ->whereIn('manager_id', $current)
                ->whereNotIn('id', array_merge($ids, [$this->id]))
                ->pluck('id')
                ->all();

            if (empty($children)) {
                break;
            }

            $ids = array_merge($ids, $children);
            $current = $children;
        }

        return array_values(array_unique($ids));
    }

    /** Collaborateurs encadrés (voir teamMemberIds). */
    public function teamMembers()
    {
        return static::withoutGlobalScopes()->whereIn('id', $this->teamMemberIds());
    }

    /**
     * Suppression d'une fiche partagée (établissement, prescripteur) :
     * administration et direction toujours ; la hiérarchie pour les fiches de son équipe ;
     * le créateur tant que la fiche n'est pas validée.
     */
    public function canDeleteSharedRecord(?string $ownerId, ?string $statutValidation): bool
    {
        if ($this->isAdminOrDirection() || ($ownerId !== null && $this->manages($ownerId))) {
            return true;
        }

        return $ownerId !== null && $ownerId === $this->id && $statutValidation !== 'valide';
    }

    /** Modification d'un élément personnel (événement…) : son auteur, sa hiérarchie, l'administration. */
    public function canEditRecordOf(?string $ownerId): bool
    {
        return $this->isAdminOrDirection()
            || ($ownerId !== null && ($ownerId === $this->id || $this->manages($ownerId)));
    }

    /** Administration et direction (périmètre global). */
    public function isAdminOrDirection(): bool
    {
        return $this->can('scope:global') || $this->hasAnyRole([
            'superadmin', 'super-admin', 'admin',
            'directeur_commercial', 'directeur-commercial',
            'directeur_marketing', 'directeur-marketing',
            'directeur_regional', 'directeur-regional',
        ]);
    }

    /** Vrai si $userId fait partie de l'équipe encadrée. */
    public function manages(string $userId): bool
    {
        return in_array($userId, $this->teamMemberIds(), true);
    }

    public function isAdminOrSupervisor(): bool
    {
        return $this->hasRole([
            'super-admin',
            'superadmin',
            'Administrateur Groupe',
            'Administrateur Pays',
            'Direction Générale',
            'Manager',
            'directeur-commercial',
            'directeur_commercial',
            'directeur-marketing',
            'directeur_marketing',
            'sales_manager',
            'superviseur',
        ]);
    }
}
