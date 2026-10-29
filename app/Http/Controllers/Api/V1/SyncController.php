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
        $request->validate(['last_sync' => 'nullable|date']);
        $lastSync = $request->query('last_sync') ?: '1970-01-01 00:00:00';
        $user = Auth::user();

        // Fonction utilitaire pour récupérer les données modifiées (le DynamicPerimeterScope applique automatiquement le RBAC)
        $getModified = function ($modelClass) use ($lastSync, $user) {
            if ($modelClass === Training::class) {
                return Training::forLearner($user->id)->where(fn ($q) => $q
                    ->where('trainings.updated_at', '>=', $lastSync)->orWhere('learner_progress.updated_at', '>=', $lastSync))->get();
            }
            $query = $modelClass::where('updated_at', '>=', $lastSync);
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($modelClass))) {
                $query->withTrashed();
            }
            if ($modelClass === Activity::class) {
                $query->where(fn ($q) => $q->whereNotIn('statut', ['brouillon', 'draft'])->orWhere('user_id', $user->id));
            }
            if ($modelClass === SampleMovement::class && !$user->isAdminOrDirection()) {
                $query->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds()));
            }
            return $query->get();
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
            'leaves' => $getModified(Activity::class)->filter(fn ($activity) => in_array($activity->planning_kind, ['conge', 'permission'], true))->map(fn($activity)=>app(\App\Services\LeaveWorkflow::class)->present($activity,$user))->values(),
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
        summary: 'Push massif de la file d\'attente hors-ligne',
        security: [['bearerAuth' => []]],
        tags: ['Synchronisation Offline'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['queue'],
                properties: [
                    new OA\Property(property: 'queue', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'endpoint', type: 'string'),
                            new OA\Property(property: 'method', type: 'string'),
                            new OA\Property(property: 'payload', type: 'object')
                        ]
                    ))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de synchronisation')
        ]
    )]
    public function push(Request $request)
    {
        $validated = $request->validate([
            'queue' => 'required|array|max:100', 'queue.*' => 'required|array',
            'queue.*.endpoint' => 'required|string|max:255', 'queue.*.method' => 'sometimes|in:POST,PUT,PATCH,DELETE,post,put,patch,delete',
            'queue.*.payload' => 'sometimes|array',
        ]);
        $queue = $validated['queue'];
        $actor = $request->user();
        
        $processed = 0;
        $failed = [];

        DB::beginTransaction();
        try {
            foreach ($queue as $index => $item) {
                $endpoint = $item['endpoint'] ?? null;
                $method = strtoupper($item['method'] ?? 'POST');
                $endpoint = preg_replace('#^/?(?:api/)?v1/#', '', ltrim($endpoint, '/'));
                if (!preg_match('#^(activities|contacts|accounts|expenses|events|tasks|tour-plans|sample-movements|sample-requests|requests|leaves|trainings|orders|sales|opportunities|objectifs|clm-analytics|pharmacovigilance-reports)(?:/[a-zA-Z0-9_-]+)*$#', $endpoint)) {
                    $failed[] = ['index' => $index, 'endpoint' => $endpoint, 'error' => 'Cette destination ne peut pas être rejouée hors ligne.'];
                    continue;
                }
                $payload = $item['payload'] ?? [];

                if (!$endpoint) {
                    $failed[] = ['index' => $index, 'error' => 'Endpoint manquant'];
                    continue;
                }

                // ── Validation stricte des horodatages (Conflits hors-ligne) ──
                if (in_array($method, ['PUT', 'PATCH']) || str_contains($endpoint, 'report')) {
                    $clientTimestamp = $payload['client_updated_at'] ?? $payload['updated_at'] ?? null;
                    if ($clientTimestamp) {
                        $modelMap = [
                            'activities' => Activity::class,
                            'contacts'   => Contact::class,
                            'accounts'   => Account::class,
                            'expenses'   => Expense::class,
                        ];

                        foreach ($modelMap as $prefix => $mClass) {
                            if (str_contains($endpoint, $prefix)) {
                                preg_match('/' . $prefix . '\/([a-zA-Z0-9\-]+)/', $endpoint, $matches);
                                $resourceId = $matches[1] ?? null;
                                if ($resourceId) {
                                    $record = $mClass::find($resourceId);
                                    if ($record && $record->updated_at) {
                                        $serverTime = Carbon::parse($record->updated_at);
                                        $clientTime = Carbon::parse($clientTimestamp);
                                        if ($serverTime->gt($clientTime) && empty($payload['force_override'])) {
                                            $failed[] = [
                                                'index' => $index,
                                                'endpoint' => $endpoint,
                                                'conflict' => true,
                                                'error' => 'Conflit de synchronisation : La ressource sur le serveur a été modifiée ultérieurement.',
                                                'server_updated_at' => $serverTime->toDateTimeString(),
                                                'client_updated_at' => $clientTime->toDateTimeString(),
                                            ];
                                            continue 2;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                }

                try {
                    // Création d'une requête interne pour simuler l'appel API
                    $internalRequest = Request::create("/api/v1/{$endpoint}", $method, $payload);
                    $internalRequest->headers->set('Accept', 'application/json');
                    $internalRequest->setUserResolver(fn() => $actor);
                    foreach (['Authorization', 'X-Country-Id', 'X-Organizational-Unit-Id'] as $header) {
                        if ($request->headers->has($header)) $internalRequest->headers->set($header, $request->header($header));
                    }

                    // Dispatch via le routeur de l'application
                    try {
                        $response = app()->handle($internalRequest);
                    } finally {
                        app()->instance('request', $request);
                        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
                        Auth::setUser($actor);
                    }

                    if ($response->getStatusCode() >= 400) {
                        throw new \Exception($response->getContent());
                    }

                    $processed++;
                } catch (\Exception $ex) {
                    $failed[] = [
                        'index' => $index,
                        'endpoint' => $endpoint,
                        'error' => $ex->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'status' => count($failed) > 0 ? 'partial_success' : 'success',
                'processed_items' => $processed,
                'errors' => $failed,
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
