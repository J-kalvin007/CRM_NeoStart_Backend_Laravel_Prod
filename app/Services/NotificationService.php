<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Envoie une notification multi-canaux (DB, Email, FCM)
     */
    public function send($users, string $title, string $message, string $type = 'info', array $data = [], ?string $actionUrl = null)
    {
        if (empty($users)) {
            return;
        }

        try {
            Notification::send($users, new SystemNotification($title, $message, $type, $data, $actionUrl));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
        }
    }
}
