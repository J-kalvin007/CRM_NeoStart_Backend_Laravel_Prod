<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\FcmNotificationService;

class TestFcmCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Teste l\'envoi d\'une notification push FCM à un utilisateur spécifique par son adresse email';

    /**
     * Execute the console command.
     */
    public function handle(FcmNotificationService $fcmService)
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("L'utilisateur avec l'email {$email} est introuvable.");
            return 1;
        }

        if (empty($user->fcm_token)) {
            $this->warn("L'utilisateur {$email} n'a pas de token FCM configuré en base de données.");
            return 1;
        }

        $this->info("Envoi d'une notification de test à {$email}...");

        $success = $fcmService->sendToUser(
            $user,
            "Notification de test \u{1F680}",
            "Ceci est un test d'intégration FCM pour Skynity CRM.",
            ['type' => 'test_push']
        );

        if ($success) {
            $this->info("Succès ! La notification a été envoyée.");
        } else {
            $this->error("Échec de l'envoi de la notification. Consultez les logs pour plus de détails.");
        }

        return 0;
    }
}
