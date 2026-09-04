<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    protected Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Envoyer une notification Push à un utilisateur via FCM.
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data Données supplémentaires pour le mobile
     * @return bool
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (empty($user->fcm_token)) {
            Log::info("Impossible d'envoyer la notification Push : l'utilisateur {$user->id} n'a pas de token FCM.");
            return false;
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            
            Log::info("Notification Push envoyée avec succès à l'utilisateur {$user->id}");
            return true;
        } catch (\Throwable $e) {
            Log::error("Erreur lors de l'envoi de la notification Push FCM à l'utilisateur {$user->id} : " . $e->getMessage());
            return false;
        }
    }
}
