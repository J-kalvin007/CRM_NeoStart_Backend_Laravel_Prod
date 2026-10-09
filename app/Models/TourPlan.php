<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class TourPlan extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'user_id',
        'nom',
        'description',
        'date_debut',
        'date_fin',
        'statut',
        'valide_par',
        'valide_le',
        'commentaire_validation',
        'nb_activites_planifiees',
        'nb_activites_realisees',
        'local_uuid',
    ];

    protected $casts = [
        'date_debut'              => 'date',
        'date_fin'                => 'date',
        'valide_le'               => 'datetime',
        'nb_activites_planifiees' => 'integer',
        'nb_activites_realisees'  => 'integer',
    ];

    /* ─── Relations ──────────────────────────────────────────── */

    public function delegue()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validePar()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /* ─── Scopes ─────────────────────────────────────────────── */

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeValide($query)
    {
        return $query->where('statut', 'valide');
    }
}

