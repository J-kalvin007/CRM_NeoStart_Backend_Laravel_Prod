<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;
use App\Notifications\MissingVisitReportNotification;
use Carbon\Carbon;

class RemindMissingReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visits:remind-missing-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind delegates to submit their missing activity reports for today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting missing reports check...');

        $activities = Activity::with('delegue')
            ->whereDate('date_activite', Carbon::today())
            ->whereNotIn('statut', ['annulee', 'manquee'])
            ->whereNotIn('report_status', ['submitted', 'validated', 'modified'])
            ->get();

        $count = 0;
        foreach ($activities as $activity) {
            if ($activity->delegue) {
                $activity->delegue->notify(new MissingVisitReportNotification($activity));
                $count++;
            }
        }

        $this->info("Found {$activities->count()} activities missing reports.");
        $this->info("Sent {$count} notifications.");
    }
}
