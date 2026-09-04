<?php

namespace App\Console\Commands;

use App\Mail\ActivityReminderMail;
use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendActivityReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie un email de rappel aux utilisateurs pour leurs visites prévues le lendemain';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        
        // Trouver toutes les activités prévues pour demain, groupées par délégué (user_id)
        $activitiesGrouped = Activity::with(['contact', 'account'])
            ->whereDate('date_activite', $tomorrow)
            ->whereIn('statut', ['brouillon', 'planifie'])
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('user_id');

        $count = 0;
        foreach ($activitiesGrouped as $userId => $activities) {
            $user = User::find($userId);
            if ($user && $user->email) {
                Mail::to($user->email)->send(new ActivityReminderMail($user, $activities));
                $count++;
            }
        }

        $this->info("Rappels envoyés avec succès à $count utilisateurs pour la journée de demain ($tomorrow).");
    }
}
