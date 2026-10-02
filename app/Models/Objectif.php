<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Objectif extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $table = 'objectifs';

    protected $fillable = [
        'user_id',
        'team_id',
        'indicator_id',
        'objectif_type_id',
        'organizational_unit_id',
        'assigned_by_user_id',
        'date_debut',
        'date_fin',
        'periode',
        'valeur_cible',
        'valeur_realisee',
        'taux_realisation',
        'product_id',
        'description',
        'statut',
    ];

    protected $casts = [
        'date_debut'       => 'date',
        'date_fin'         => 'date',
        'valeur_cible'     => 'float',
        'valeur_realisee'  => 'float',
        'taux_realisation' => 'float',
    ];

    /* ─── Relations ──────────────────────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }
    
    public function type()
    {
        return $this->belongsTo(ObjectifType::class, 'objectif_type_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    /* ─── Business Logic ─────────────────────────────────────── */

    /**
     * Recalcule et sauvegarde le taux de réalisation.
     */
    public function recalculerTaux(): void
    {
        if ($this->valeur_cible > 0) {
            $taux = ($this->valeur_realisee / $this->valeur_cible) * 100;
            $taux = round($taux, 2);
            $statut = $this->statut;
            
            if ($statut !== 'annule') {
                if ($taux >= 100) {
                    $statut = $taux == 100 ? 'atteint' : 'depasse';
                } else {
                    // if end date is in the past, it's not achieved. Otherwise active.
                    if ($this->date_fin && now()->startOfDay()->greaterThan(\Carbon\Carbon::parse($this->date_fin)->startOfDay())) {
                        $statut = 'non_atteint';
                    } else {
                        $statut = 'actif';
                    }
                }
            }
            
            $this->update(['taux_realisation' => $taux, 'statut' => $statut]);
        }
    }
}

