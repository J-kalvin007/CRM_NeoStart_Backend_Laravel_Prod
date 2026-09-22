<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\FcmNotificationService;

class FcmChannel
{
    protected FcmNotificationService $fcm;

    public function __construct(FcmNotificationService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        if ($message) {
            $this->fcm->sendToUser(
                $notifiable, 
                $message['title'], 
                $message['body'], 
                $message['data'] ?? []
            );
        }
    }
}
