<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasPerimeter;

class Activity extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPerimeter;
    use HasActivityLogs;

    protected $fillable = [
        'tour_plan_id',
        'campaign_id',
        'user_id',
        'contact_id',
        'account_id',
        'activity_type',
        'date_activite',
        'heure_debut',
        'heure_fin',
        'duree_minutes',
        'gps_debut_lat',
        'gps_debut_lng',
        'gps_fin_lat',
        'gps_fin_lng',
        'notes',
        'prochain_rdv',
        'statut',
        'is_locked',
        'locked_at',
        'gps_flagged',
        'local_uuid',
    ];

    protected $casts = [
        'date_activite'  => 'date',
        'gps_debut_lat'  => 'float',
        'gps_debut_lng'  => 'float',
        'gps_fin_lat'    => 'float',
        'gps_fin_lng'    => 'float',
        'duree_minutes'  => 'integer',
        'is_locked'      => 'boolean',
        'locked_at'      => 'datetime',
        'gps_flagged'    => 'boolean',
    ];

    /* ─── Relations ──────────────────────────────────────────── */

    public function tourPlan()
    {
        return $this->belongsTo(TourPlan::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function delegue()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accompanyingUser()
    {
        return $this->belongsTo(User::class, 'accompanying_user_id');
    }
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'activity_product')
            ->withPivot('quantity', 'customer_interest', 'remarques')
            ->withTimestamps();
    }

    public function sampleMovements()
    {
        return $this->hasMany(SampleMovement::class);
    }

    /* ─── Business Logic ─────────────────────────────────────── */

    /**
     * Marque automatiquement comme 'manquee' les activités dont l'heure/date prévue est dépassée sans réalisation.
     */
    public static function markMissedPastActivities(): void
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');

        // 1. Visites sur des dates antérieures non réalisées
        static::whereDate('date_activite', '<', $today)
            ->whereNotIn('statut', ['realisee', 'effectuee', 'manquee', 'annulee'])
            ->where(function ($q) {
                $q->whereNull('report_status')
                  ->orWhereNotIn('report_status', ['submitted', 'validated', 'approved']);
            })
            ->update(['statut' => 'manquee']);

        // 2. Visites d'aujourd'hui dont l'heure prévue (+ 30 minutes) est dépassée
        $todayActivities = static::whereDate('date_activite', $today)
            ->whereNotIn('statut', ['realisee', 'effectuee', 'manquee', 'annulee'])
            ->where(function ($q) {
                $q->whereNull('report_status')
                  ->orWhereNotIn('report_status', ['submitted', 'validated', 'approved']);
            })
            ->get();

        foreach ($todayActivities as $act) {
            $timeStr = $act->heure_fin ?: $act->heure_debut;
            if ($timeStr) {
                try {
                    $scheduledTime = \Carbon\Carbon::parse($today . ' ' . $timeStr)->addMinutes(30)->format('H:i:s');
                    if ($currentTime > $scheduledTime) {
                        $act->update(['statut' => 'manquee']);
                    }
                } catch (\Exception $e) {}
            }
        }
    }

    /**
     * Verrouille l'activité après réalisation (empêche toute modification).
     */
    public function lock(): void
    {
        $this->update(['is_locked' => true, 'locked_at' => now()]);
    }
}

