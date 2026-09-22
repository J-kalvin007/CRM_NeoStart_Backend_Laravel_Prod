<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if ($user) {
            Notification::create([
                'type' => 'App\\Notifications\\TourPlanApprovedNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => [
                    'title' => 'Nouveau TourPlan validé',
                    'message' => 'Votre manager a approuvé votre plan de tournée pour la semaine 38.',
                ],
                'read_at' => null
            ]);

            Notification::create([
                'type' => 'App\\Notifications\\StockWarningNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => [
                    'title' => 'Rappel Stock Échantillons',
                    'message' => 'Votre stock de CardioProtect 50mg est inférieur à 10 unités.',
                ],
                'read_at' => now()
            ]);
        }
    }
}
