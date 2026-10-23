<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\FcmNotificationService;

class FcmChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (empty($notifiable->fcm_token) || !method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        if ($message) {
            try {
                app(FcmNotificationService::class)->sendToUser(
                    $notifiable,
                    $message['title'],
                    $message['body'],
                    $message['data'] ?? []
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Push notification unavailable: ' . $e->getMessage());
            }
        }
    }
}
