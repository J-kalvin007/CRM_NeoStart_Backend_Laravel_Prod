<!DOCTYPE html>
<html>
<head>
    <title>Vos visites prévues pour demain</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #2563EB;">Bonjour {{ $user->name }},</h2>
        <p>Voici un récapitulatif de vos visites planifiées pour demain :</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background-color: #f3f4f6; text-align: left;">
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Heure</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Contact / Établissement</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activities as $activity)
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            {{ $activity->heure_debut ? \Carbon\Carbon::parse($activity->heure_debut)->format('H:i') : '--:--' }}
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            @if($activity->contact)
                                <strong>{{ $activity->contact->titre }} {{ $activity->contact->nom }} {{ $activity->contact->prenom }}</strong>
                                @if($activity->account)
                                    <br><span style="font-size: 0.9em; color: #666;">({{ $activity->account->nom }})</span>
                                @endif
                            @elseif($activity->account)
                                <strong>{{ $activity->account->nom }}</strong>
                            @else
                                Non spécifié
                            @endif
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            {{ ucfirst($activity->activity_type ?? 'Visite') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $frontendUrl }}/activities" style="background-color: #2563EB; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Consulter mon planning
            </a>
        </div>
        
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #9CA3AF;">Ceci est un message automatique, merci de ne pas y répondre.</p>
    </div>
</body>
</html>
