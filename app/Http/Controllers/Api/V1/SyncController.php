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
use App\Models\Account;
use App\Models\Product;
use App\Models\Document;
use App\Models\DigitalSupport;
use App\Models\Event;
use App\Models\TourPlan;
use App\Models\Notification;
use App\Models\SampleMovement;
use App\Models\Campaign;
use App\Models\Opportunity;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\Leave;
use App\Models\Training;
use App\Models\Conversation;
use App\Models\Objectif;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Synchronisation Offline', description: 'API de synchronisation pour le mode hors ligne mobile')]
class SyncController extends Controller
{
    #[OA\Get(
        path: '/api/v1/sync/pull',
        summary: 'Pull des données pour le mode offline',
        security: [['bearerAuth' => []]],
        tags: ['Synchronisation Offline'],
        parameters: [
            new OA\Parameter(name: 'last_sync', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Données synchronisées')
        ]
    )]
    public function pull(Request $request)
    {
        $lastSync = $request->query('last_sync', '1970-01-01 00:00:00');
        $user = Auth::user();

        // Fonction utilitaire pour récupérer les données modifiées (le PerimeterScope applique automatiquement le RBAC)
        $getModified = function ($modelClass) use ($lastSync) {
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($modelClass))) {
                return $modelClass::withTrashed()->where('updated_at', '>=', $lastSync)->get();
            }
            return $modelClass::where('updated_at', '>=', $lastSync)->get();
        };

        // Fonction utilitaire pour récupérer les données supprimées
        $getDeleted = function ($modelClass) use ($lastSync) {
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($modelClass))) {
                return $modelClass::onlyTrashed()->where('deleted_at', '>=', $lastSync)->pluck('id');
            }
            return [];
        };

        $data = [
            'contacts' => $getModified(Contact::class),
            'accounts' => $getModified(Account::class),
            'products' => $getModified(Product::class),
            'activities' => $getModified(Activity::class),
            'tour_plans' => $getModified(TourPlan::class),
            'tasks' => $getModified(Task::class),
            'documents' => $getModified(Document::class),
            'digital_supports' => $getModified(DigitalSupport::class),
            'events' => $getModified(Event::class),
            'notifications' => Notification::where('notifiable_id', $user->id)->where('updated_at', '>=', $lastSync)->get(),
            'sample_movements' => $getModified(SampleMovement::class),
            'campaigns' => $getModified(Campaign::class),
            'opportunities' => $getModified(Opportunity::class),
            'orders' => $getModified(Order::class),
            'sales' => $getModified(Sale::class),
            'expenses' => $getModified(Expense::class),
            'leaves' => $getModified(Leave::class),
            'trainings' => $getModified(Training::class),
            'objectifs' => $getModified(Objectif::class),
        ];

        $deleted = [
            'contacts' => $getDeleted(Contact::class),
            'accounts' => $getDeleted(Account::class),
            'activities' => $getDeleted(Activity::class),
            'tasks' => $getDeleted(Task::class),
            'tour_plans' => $getDeleted(TourPlan::class),
            'objectifs' => $getDeleted(Objectif::class),
        ];

        return response()->json([
            'timestamp' => Carbon::now()->toDateTimeString(),
            'data' => $data,
            'deleted' => $deleted
        ]);
    }

    #[OA\Post(
        path: '/api/v1/sync/push',
        summary: 'Push des modifications offline',
        security: [['bearerAuth' => []]],
        tags: ['Synchronisation Offline'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de synchronisation')
        ]
    )]
    public function push(Request $request)
    {
        $payload = $request->all();
        $user = Auth::user();
        
        $processed = [];
        $failed = [];
        $mapping = [];

        DB::beginTransaction();
        try {
            // Mapping générique pour traiter les entités
            $entitiesToProcess = [
                'contacts' => Contact::class,
                'accounts' => Account::class,
                'activities' => Activity::class,
                'tasks' => Task::class,
                'tour_plans' => TourPlan::class,
                'expenses' => Expense::class,
                'leaves' => Leave::class,
                'sample_movements' => SampleMovement::class,
                'objectifs' => Objectif::class,
            ];

            foreach ($entitiesToProcess as $key => $modelClass) {
                $processed[$key] = 0;
                if (!empty($payload[$key])) {
                    foreach ($payload[$key] as $itemData) {
                        try {
                            $localUuid = $itemData['local_uuid'] ?? null;
                            $id = $itemData['id'] ?? null;

                            // Rechercher par id ou local_uuid
                            $model = null;
                            if ($id) {
                                $model = $modelClass::find($id);
                            }
                            if (!$model && $localUuid) {
                                // Important: on utilise where car firstWhere peut planter si la colonne n'existe pas
                                $model = $modelClass::where('local_uuid', $localUuid)->first();
                            }

                            // Nettoyage des données
                            unset($itemData['id']); // On ne force pas l'ID serveur
                            $itemData['user_id'] = $user->id;
                            if (in_array('subsidiary_id', (new $modelClass)->getFillable())) {
                                $itemData['subsidiary_id'] = $user->subsidiary_id;
                            }

                            if ($model) {
                                $model->update($itemData);
                                $mapping[$localUuid] = $model->id;
                            } else {
                                $newModel = $modelClass::create($itemData);
                                if ($localUuid) {
                                    $mapping[$localUuid] = $newModel->id;
                                }
                            }
                            $processed[$key]++;
                        } catch (\Exception $ex) {
                            $failed[] = [
                                'entity' => $key,
                                'local_uuid' => $itemData['local_uuid'] ?? null,
                                'errors' => $ex->getMessage()
                            ];
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => count($failed) > 0 ? 'partial_success' : 'success',
                'processed' => $processed,
                'failed' => $failed,
                'mapping' => $mapping,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error', 
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
