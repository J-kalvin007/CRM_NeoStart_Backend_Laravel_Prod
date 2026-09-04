<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Objectif extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;

    protected $table = 'objectifs';

    protected $fillable = [
        'user_id',
        'team_id',
        'date_debut',
        'date_fin',
        'periode',
        'type_objectif',
        'valeur_cible',
        'valeur_realisee',
        'unite',
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

    /* ─── Business Logic ─────────────────────────────────────── */

    /**
     * Recalcule et sauvegarde le taux de réalisation.
     */
    public function recalculerTaux(): void
    {
        if ($this->valeur_cible > 0) {
            $taux = ($this->valeur_realisee / $this->valeur_cible) * 100;
            $this->update(['taux_realisation' => round($taux, 2)]);
        }
    }
}

