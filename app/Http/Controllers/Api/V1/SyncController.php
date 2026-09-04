<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Contact;
use App\Models\Task;
use App\Models\Activity;

class SyncController extends Controller
{
    /**
     * PULL: Le mobile demande les données modifiées depuis sa dernière synchronisation.
     */
    public function pull(Request $request)
    {
        $lastSync = $request->query('last_sync', '1970-01-01 00:00:00');
        $user = Auth::user();

        // Récupérer les entités créées/modifiées ou supprimées depuis le last_sync
        $data = [
            'contacts' => Contact::withTrashed()->where('updated_at', '>=', $lastSync)->get(),
            'tasks' => Task::withTrashed()->where('updated_at', '>=', $lastSync)->get(),
            'activities' => Activity::withTrashed()->where('updated_at', '>=', $lastSync)->get(),
            // On peut ajouter Accounts, Products, Documents, etc.
        ];

        return response()->json([
            'timestamp' => Carbon::now()->toDateTimeString(),
            'data' => $data
        ]);
    }

    /**
     * PUSH: Le mobile envoie un lot de modifications faites en mode "Offline".
     */
    public function push(Request $request)
    {
        $payload = $request->validate([
            'tasks' => 'nullable|array',
            'activities' => 'nullable|array',
        ]);

        $user = Auth::user();
        $processed = ['tasks' => 0, 'activities' => 0];
        $errors = [];

        DB::beginTransaction();
        try {
            // Traitement des tâches envoyées en offline
            if (!empty($payload['tasks'])) {
                foreach ($payload['tasks'] as $taskData) {
                    $task = Task::find($taskData['id']);
                    if ($task) {
                        $task->update($taskData);
                    } else {
                        // Si le mobile a généré son propre UUID, on force l'insertion
                        $taskData['user_id'] = $user->id;
                        $taskData['subsidiary_id'] = $user->subsidiary_id;
                        Task::create($taskData);
                    }
                    $processed['tasks']++;
                }
            }

            // Traitement des activités (visites) envoyées en offline
            if (!empty($payload['activities'])) {
                foreach ($payload['activities'] as $activityData) {
                    $activity = Activity::find($activityData['id']);
                    if ($activity) {
                        $activity->update($activityData);
                    } else {
                        $activityData['user_id'] = $user->id;
                        $activityData['subsidiary_id'] = $user->subsidiary_id;
                        Activity::create($activityData);
                    }
                    $processed['activities']++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'processed' => $processed,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
