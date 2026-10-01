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

    /**
     * IDs des collaborateurs encadrés (récursif via manager_id) :
     * un superviseur obtient ses délégués, un directeur ses superviseurs et leurs délégués.
     */
    public function teamMemberIds(): array
    {
        $ids = [];
        $current = [$this->id];

        while (!empty($current)) {
            $children = static::withoutGlobalScopes()
                ->whereIn('manager_id', $current)
                ->whereNotIn('id', array_merge($ids, [$this->id]))
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $children);
            $current = $children;
        }

        return $ids;
    }

    /** Collaborateurs encadrés (voir teamMemberIds). */
    public function teamMembers()
    {
        return static::withoutGlobalScopes()->whereIn('id', $this->teamMemberIds());
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
            'superviseur'
        ]);
    }
}
