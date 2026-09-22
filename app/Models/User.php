<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\HasPerimeter;
use App\Models\Country;
use App\Models\Subsidiary;
use App\Models\Region;
use App\Models\Territory;
use App\Models\Zone;
use App\Models\Sector;
use App\Models\Team;

class User extends Authenticatable
{
    use HasActivityLogs;
    use HasApiTokens, HasFactory, Notifiable, HasUuids, HasRoles, HasPerimeter;

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
        'country_id',
        'subsidiary_id',
        'region_id',
        'territory_id',
        'zone_id',
        'sector_id',
        'team_id',
        'manager_id',
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
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'last_login_at' => 'datetime',
            'force_password_change' => 'boolean',
            'otp_enabled' => 'boolean',
        ];
    }

    public function scopes()
    {
        return $this->belongsToMany(OrganizationalUnit::class, 'user_scopes', 'user_id', 'organizational_unit_id')->withTimestamps();
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'subsidiary_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function territory()
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
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
            'superviseur'
        ]);
    }
}
