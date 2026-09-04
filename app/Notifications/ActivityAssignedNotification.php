<?php

namespace App\Notifications;

use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Activity $activity
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contactName = $this->activity->contact ? $this->activity->contact->nom . ' ' . $this->activity->contact->prenom : 'Non spécifié';
        $date = $this->activity->date_activite ? $this->activity->date_activite->format('d/m/Y') : 'Non planifiée';

        $url = config('app.frontend_url', 'http://localhost:3000') . "/activities/{$this->activity->id}";

        return (new MailMessage)
                    ->subject('Nouvelle Visite Assignée')
                    ->greeting('Bonjour ' . $notifiable->name . ',')
                    ->line('Une nouvelle visite terrain vous a été assignée.')
                    ->line('Contact : ' . $contactName)
                    ->line('Date prévue : ' . $date)
                    ->action('Voir les détails', $url)
                    ->line('Merci de consulter votre planning.');
    }

    public function toArray(object $notifiable): array
    {
        $contactName = $this->activity->contact ? $this->activity->contact->nom . ' ' . $this->activity->contact->prenom : 'Non spécifié';
        
        return [
            'activity_id' => $this->activity->id,
            'message' => "Nouvelle visite assignée : $contactName",
            'type' => 'activity_assigned'
        ];
    }
}
