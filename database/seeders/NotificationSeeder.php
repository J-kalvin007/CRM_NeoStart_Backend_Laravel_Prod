<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('email', 'cmoucharif@gmail.com')->first() ?? User::whereHas('roles', fn($q) => $q->whereIn('name', ['manager', 'superviseur']))->first();
        $delegue = User::where('email', 'adommoucha@gmail.com')->first() ?? User::whereHas('roles', fn($q) => $q->whereIn('name', ['delegue', 'delegue_medical', 'Délégué Médical']))->first();

        // 1. Notifications pour le Manager
        if ($manager) {
            $managerNotifications = [
                [
                    'title' => 'Planning de tournée soumis pour validation',
                    'message' => 'Le délégué COZI Adom Moucharif a soumis son plan de tournée hebdomadaire.',
                    'type' => 'planning',
                    'recipient_role' => 'manager',
                    'read_at' => null,
                ],
                [
                    'title' => 'Nouveau compte-rendu de visite soumis',
                    'message' => 'Un rapport de visite auprès de Polyclinique Sainte Anne nécessite votre approbation.',
                    'type' => 'report',
                    'recipient_role' => 'manager',
                    'read_at' => null,
                ],
                [
                    'title' => 'Nouvelle note de frais soumise',
                    'message' => 'Une note de frais de 25 000 FCFA a été soumise par un délégué de votre équipe.',
                    'type' => 'expense',
                    'recipient_role' => 'manager',
                    'read_at' => now()->subHours(2),
                ],
                [
                    'title' => 'Nouvelle fiche prescripteur créée',
                    'message' => 'La création de la fiche du Dr. Marc Adjanohoun est en attente de votre modération.',
                    'type' => 'contact',
                    'recipient_role' => 'manager',
                    'read_at' => now()->subHours(5),
                ],
            ];

            foreach ($managerNotifications as $notif) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\Notifications\GeneralNotification',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $manager->id,
                    'data' => json_encode([
                        'title' => $notif['title'],
                        'message' => $notif['message'],
                        'type' => $notif['type'],
                        'recipient_role' => $notif['recipient_role'],
                    ]),
                    'read_at' => $notif['read_at'],
                    'created_at' => now()->subMinutes(rand(10, 180)),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Notifications pour le Délégué
        if ($delegue) {
            $delegueNotifications = [
                [
                    'title' => 'Visite médicale validée',
                    'message' => 'Votre responsable a validé votre visite planifiée au CHU de Cocody.',
                    'type' => 'planning',
                    'recipient_role' => 'delegate',
                    'read_at' => null,
                ],
                [
                    'title' => 'Planning de tournée approuvé',
                    'message' => 'Votre manager a approuvé votre plan de tournée pour la semaine en cours.',
                    'type' => 'planning',
                    'recipient_role' => 'delegate',
                    'read_at' => null,
                ],
                [
                    'title' => 'Compte-rendu approuvé',
                    'message' => 'Votre compte-rendu de visite pour Dr. Marc Adjanohoun a été validé.',
                    'type' => 'report',
                    'recipient_role' => 'delegate',
                    'read_at' => now()->subHours(3),
                ],
                [
                    'title' => 'Note de frais validée',
                    'message' => 'Votre note de frais de transport a été validée et transmise à la comptabilité.',
                    'type' => 'expense',
                    'recipient_role' => 'delegate',
                    'read_at' => now()->subHours(6),
                ],
            ];

            foreach ($delegueNotifications as $notif) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\Notifications\GeneralNotification',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $delegue->id,
                    'data' => json_encode([
                        'title' => $notif['title'],
                        'message' => $notif['message'],
                        'type' => $notif['type'],
                        'recipient_role' => $notif['recipient_role'],
                    ]),
                    'read_at' => $notif['read_at'],
                    'created_at' => now()->subMinutes(rand(10, 180)),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
