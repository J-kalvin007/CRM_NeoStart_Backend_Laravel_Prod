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

    protected $appends = ['planning_kind', 'activity_type_name'];

    public function getPlanningKindAttribute(): string
    {
        $code = $this->activity_type_id ? $this->activityType?->code : $this->activity_type;
        return \App\Services\PlanningOptions::kind($code);
    }

    public function getActivityTypeNameAttribute(): string
    {
        return $this->activityType?->name ?? $this->getAttribute('activity_type') ?? 'Visite';
    }

    public function isCalendarActivity(): bool
    {
        return $this->planning_kind !== 'visite';
    }

    public function scopeCalendar($query, array $codes = \App\Services\PlanningOptions::CALENDAR_CODES)
    {
        $names = $codes;
        foreach (['rcp' => 'RCP', 'reunion' => 'Réunion', 'conge' => 'Congé', 'permission' => 'Permission'] as $code => $label) {
            if (in_array($code, $codes, true)) $names[] = $label;
        }
        if (in_array('conge', $codes, true)) {
            $names = array_merge($names, ['Congé', 'absence', 'indisponibilite', 'leave', 'Congé Annuel', 'Congé Maladie', 'Congé Payé', 'RTT', 'Formation', 'Événement Familial', 'Arrêt maladie']);
        }
        return $query->where(fn ($q) => $q->whereIn('activity_type', $names)
            ->orWhereHas('activityType', fn ($type) => $type->whereIn('code', $codes)));
    }

    public function scopeVisits($query)
    {
        return $query->whereNot(fn ($q) => $q->calendar());
    }

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
        'date_fin',
        'outside_weekly_plan',
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
        'date_fin' => 'date',
        'outside_weekly_plan' => 'boolean',
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

    /** Type d'activité dynamique (activity_types) */
    public function activityType()
    {
        return $this->belongsTo(\App\Models\ActivityType::class, 'activity_type_id');
    }

    public function type()
    {
        return $this->belongsTo(\App\Models\ActivityType::class, 'activity_type_id');
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
        static::visits()->whereRaw('COALESCE(date_fin, date_activite) < ?', [$today])
            ->whereNotIn('statut', ['realisee', 'effectuee', 'manquee', 'annulee', 'en_cours'])
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
    public static function checkOverlap(string $userId, mixed $date, ?string $startTime = null, ?string $endTime = null, ?int $durationMinutes = null, ?string $excludeId = null, bool $unavailability = false, ?string $endDate = null): bool
    {
        $date = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $start = \Illuminate\Support\Carbon::parse($date . ' ' . ($startTime ?: '00:00'));
        $end = $endTime || $endDate
            ? \Illuminate\Support\Carbon::parse(($endDate ?: $date) . ' ' . ($endTime ?: '23:59:59'))
            : ($unavailability ? $start->copy()->endOfDay() : $start->copy()->addMinutes($durationMinutes ?: 30));
        // Les visites peuvent partager un créneau. Une absence, elle, réserve sa période.
        $query = static::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $userId)
            ->whereIn('statut', ['brouillon', 'en_attente', 'planifiee', 'validee', 'en_cours', 'realisee'])
            ->whereDate('date_activite', '<=', $end->toDateString())
            ->whereRaw('COALESCE(date_fin, date_activite) >= ?', [$date])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));
        if (!$unavailability) $query->calendar(['conge', 'permission']);
        return $query->get()->contains(function ($activity) use ($start, $end) {
            $activityStart = \Illuminate\Support\Carbon::parse($activity->date_activite->toDateString() . ' ' . ($activity->heure_debut ?: '00:00'));
            $activityEnd = \Illuminate\Support\Carbon::parse(($activity->date_fin ?? $activity->date_activite)->toDateString() . ' ' . ($activity->heure_fin ?: '23:59:59'));
            return $start->lt($activityEnd) && $end->gt($activityStart);
        });
    }
}
