<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingValidationNotification extends Notification
{
    use Queueable;

    public $entity;
    public $delegate;
    public $typeLabel;

    /**
     * Create a new notification instance.
     */
    public function __construct($entity, $delegate)
    {
        $this->entity = $entity;
        $this->delegate = $delegate;
        $this->typeLabel = $entity instanceof \App\Models\Contact ? 'Prescripteur' : 'Établissement';
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
        $url = config('app.frontend_url', url('/')) . "/validation/{$this->entity->id}";

        return (new MailMessage)
                    ->subject("Nouveau {$this->typeLabel} en attente de validation")
                    ->greeting("Bonjour {$notifiable->name},")
                    ->line("Le délégué {$this->delegate->name} vient d'enregistrer un(e) nouveau/nouvelle {$this->typeLabel} : **{$this->entity->nom}**.")
                    ->line("Cette fiche est actuellement en attente de validation de votre part.")
                    ->action('Voir la fiche et valider', $url)
                    ->line('Merci de traiter cette demande au plus vite.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pending_validation',
            'entity_type' => get_class($this->entity),
            'entity_id' => $this->entity->id,
            'message' => "Le délégué {$this->delegate->name} a créé un(e) {$this->typeLabel} : {$this->entity->nom}. En attente de votre validation.",
        ];
    }
}
