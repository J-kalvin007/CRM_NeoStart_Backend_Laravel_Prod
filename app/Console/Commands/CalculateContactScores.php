<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateContactScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcule le score et le potentiel des contacts en fonction des visites des 30 derniers jours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();

        // On récupère tous les contacts avec le compte du nombre de visites clôturées ces 30 derniers jours
        $contacts = Contact::withCount(['activities' => function ($query) use ($thirtyDaysAgo) {
            $query->where('statut', 'realisee')
                  ->whereDate('date_activite', '>=', $thirtyDaysAgo);
        }])->get();

        $updatedCount = 0;

        foreach ($contacts as $contact) {
            $visitCount = $contact->activities_count;

            $newPotentiel = 'faible';
            $newScore = 10;

            if ($visitCount >= 3) {
                $newPotentiel = 'fort';
                $newScore = 100;
            } elseif ($visitCount >= 1) {
                $newPotentiel = 'moyen';
                $newScore = 50;
            }

            // On met à jour uniquement s'il y a un changement pour éviter des requêtes inutiles
            if ($contact->potentiel !== $newPotentiel || $contact->score !== $newScore) {
                $contact->update([
                    'potentiel' => $newPotentiel,
                    'score' => $newScore
                ]);
                $updatedCount++;
            }
        }

        $this->info("Scoring terminé : $updatedCount contacts mis à jour.");
    }
}
