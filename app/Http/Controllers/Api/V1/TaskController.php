<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks (Agenda).
     */
    public function index(Request $request)
    {
        // On récupère les tâches, en respectant le DynamicPerimeterScope
        $query = Task::with(['contact', 'account', 'user']);

        // Filtres basiques pour l'agenda
        if ($request->has('date')) {
            $query->whereDate('start_time', $request->date);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(15));
    }

    /**
     * Store a newly created task in the agenda.
     */
    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        
        // Assigner la tâche à l'utilisateur courant par défaut, 
        // avec son périmètre.
        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $data['subsidiary_id'] = Auth::user()->subsidiary_id;
        $data['territory_id'] = Auth::user()->territory_id;

        $task = Task::create($data);

        return response()->json($task->load(['contact', 'account']), 201);
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task)
    {
        return response()->json($task->load(['contact', 'account', 'user']));
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return response()->json($task->load(['contact', 'account']));
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task)
    {
        $task->delete();
        
        return response()->json(null, 204);
    }
}
