<?php

namespace App\Models\Traits;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

trait HasActivityLogs
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // Surveille tous les champs modifiés ($fillable)
            ->logOnlyDirty() // N'enregistre que les valeurs qui ont réellement changé
            ->dontSubmitEmptyLogs() // Si aucune modification, on ne crée pas de log vide
            ->logExcept(['taux_realisation']); // Ne pas logger le taux de réalisation (champ calculé)
    }
}
