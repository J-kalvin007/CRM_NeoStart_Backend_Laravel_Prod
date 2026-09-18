<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Activity;

class MissingVisitReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $activity;

    /**
     * Create a new notification instance.
     */
    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rappel : Compte rendu de visite manquant')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Vous avez une visite réalisée le ' . $this->activity->date_activite->format('d/m/Y') . ' pour laquelle le compte rendu n\'a pas encore été soumis.')
            ->line('Merci de bien vouloir soumettre votre compte rendu dès que possible.')
            ->action('Voir la visite', url('/activities/' . $this->activity->id))
            ->line('Merci de votre collaboration !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'activity_id' => $this->activity->id,
            'message' => 'Compte rendu manquant pour la visite du ' . $this->activity->date_activite->format('d/m/Y'),
            'type' => 'missing_report'
        ];
    }
}
