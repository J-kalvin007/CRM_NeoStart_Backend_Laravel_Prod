<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory, HasUuids;
    use HasActivityLogs;

    protected $fillable = [
        'name',
        'sector_id',
        'manager_id',
    ];

    /**
     * Membres de l'équipe : collaborateurs directement rattachés au responsable de l'équipe
     * (users.team_id n'existe plus, la hiérarchie passe par users.manager_id).
     */
    public function members()
    {
        return $this->hasMany(User::class, 'manager_id', 'manager_id')
            ->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
