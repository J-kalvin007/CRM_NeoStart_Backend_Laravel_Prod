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
        'accompanying_user_id',
        'is_duo',
        'is_trio',
        'duo_user_id',
        'duo_activity_type_id',
        'duo_status',
        'duo_rejection_reason',
        'duo_delegue_status',
        'duo_delegue_rejection_reason',
        'contact_id',
        'account_id',
        'activity_type',
        'activity_type_id',
        'activity_result_id',
        'report_status',
        'report_content',
        'validated_by_id',
        'validation_date',
        'rejection_reason',
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
        'signature_base64',
        'niveau_interet',
        'motif',
        'meta',
        'gps_debut_accuracy',
        'gps_fin_accuracy',
        'start_time',
        'end_time',
        'signature',
    ];

    protected $casts = [
        'date_activite'   => 'date',
        'gps_debut_lat'   => 'float',
        'gps_debut_lng'   => 'float',
        'gps_fin_lat'     => 'float',
        'gps_fin_lng'     => 'float',
        'meta'            => 'array',
        'duree_minutes'   => 'integer',
        'is_locked'       => 'boolean',
        'locked_at'       => 'datetime',
        'start_time'      => 'datetime',
        'end_time'        => 'datetime',
        'gps_flagged'     => 'boolean',
        'validation_date' => 'datetime',
        'is_duo'          => 'boolean',
        'is_trio'         => 'boolean',
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

    /** Superviseur / manager participant à la visite DUO */
    public function duoUser()
    {
        return $this->belongsTo(User::class, 'duo_user_id');
    }

    /** Nature du DUO (duo_activity_types : coaching, formation) */
    public function duoActivityType()
    {
        return $this->belongsTo(\App\Models\DuoActivityType::class, 'duo_activity_type_id');
    }

    /** DUO actif : proposé/accepté et non refusé par le délégué */
    public function hasActiveDuo(): bool
    {
        return (bool) $this->is_duo && $this->duo_delegue_status !== 'rejected';
    }

    public function isTrio(): bool
    {
        return (bool) $this->is_trio;
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by_id');
    }
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'activity_contact', 'activity_id', 'contact_id')
                    ->withTimestamps();
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'activity_product')
            ->using(ActivityProduct::class)
            ->withPivot('quantity', 'customer_interest', 'remarques')
            ->withTimestamps();
    }

    public function sampleMovements()
    {
        return $this->hasMany(SampleMovement::class);
    }

    public function coachingSessions()
    {
        return $this->hasMany(CoachingSession::class);
    }

    public function objections()
    {
        return $this->belongsToMany(Objection::class, 'activity_objections')
            ->withPivot(['physician_reaction', 'notes'])
            ->withTimestamps();
    }

    /* ─── Business Logic ─────────────────────────────────────── */

    /**
     * Marque automatiquement comme 'manquee' les activités dont l'heure/date prévue est dépassée sans réalisation.
     */
    public static function markMissedPastActivities(): void
    {
        $today = now()->toDateString();

        // Visites sur des dates antérieures révolues non réalisées
        static::whereDate('date_activite', '<', $today)
            ->whereNotIn('statut', ['realisee', 'effectuee', 'manquee', 'annulee'])
            ->update(['statut' => 'manquee']);
    }

    /**
     * Verrouille l'activité après réalisation (empêche toute modification).
     */
    public function lock(): void
    {
        $this->update(['is_locked' => true, 'locked_at' => now()]);
    }

    /**
     * Détection de chevauchement horaire (partiel, identique ou total) pour un délégué.
     * Exclut les visites annulées ou rejetées.
     */
    public static function checkOverlap(string $userId, mixed $date, ?string $startTime = null, ?string $endTime = null, ?int $durationMinutes = null, ?string $excludeId = null): bool
    {
        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : date('Y-m-d', strtotime((string)$date));

        if (empty($startTime)) {
            $startSec = strtotime("1970-01-01 00:00:00 UTC");
            $endSec   = strtotime("1970-01-01 23:59:59 UTC");
        } else {
            $startSec = strtotime("1970-01-01 {$startTime} UTC");
            if (!empty($endTime)) {
                $endSec = strtotime("1970-01-01 {$endTime} UTC");
            } elseif (!empty($durationMinutes)) {
                $endSec = $startSec + ($durationMinutes * 60);
            } else {
                $endSec = $startSec + 3600; // Défaut 1h
            }
        }

        $formattedStart = date('H:i:s', $startSec);
        $formattedEnd   = date('H:i:s', $endSec);

        $activities = static::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->where('user_id', $userId)
            ->whereDate('date_activite', $dateStr)
            ->whereNotIn('statut', ['annulee', 'rejetee', 'refusee', 'cancelled', 'rejected'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();

        foreach ($activities as $act) {
            if (empty($act->heure_debut)) {
                $actStartSec = strtotime("1970-01-01 00:00:00 UTC");
                $actEndSec   = strtotime("1970-01-01 23:59:59 UTC");
            } else {
                $actStartSec = strtotime("1970-01-01 {$act->heure_debut} UTC");
                if (!empty($act->heure_fin)) {
                    $actEndSec = strtotime("1970-01-01 {$act->heure_fin} UTC");
                } elseif (!empty($act->duree_minutes)) {
                    $actEndSec = $actStartSec + ($act->duree_minutes * 60);
                } else {
                    $actEndSec = $actStartSec + 3600;
                }
            }

            $actStart = date('H:i:s', $actStartSec);
            $actEnd   = date('H:i:s', $actEndSec);

            // Condition de chevauchement : DébutA < FinB ET FinA > DébutB
            if ($formattedStart < $actEnd && $formattedEnd > $actStart) {
                return true;
            }
        }

        return false;
    }
}

