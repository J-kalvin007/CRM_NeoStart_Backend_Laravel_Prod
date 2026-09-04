<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Return trainings with user progress
        $trainings = DB::table('trainings')
            ->leftJoin('training_progress', function($join) use ($user) {
                $join->on('trainings.id', '=', 'training_progress.training_id')
                     ->where('training_progress.user_id', '=', $user->id);
            })
            ->select(
                'trainings.id',
                'trainings.title',
                'trainings.description',
                'trainings.type',
                'trainings.duration_minutes',
                'trainings.thumbnail_url',
                'trainings.file_url',
                'trainings.category',
                DB::raw('COALESCE(training_progress.progress, 0) as progress'),
                DB::raw("COALESCE(training_progress.status, 'not_started') as status")
            )
            ->orderByDesc('trainings.created_at')
            ->get();

        return response()->json(['data' => $trainings]);
    }

    public function show(string $id)
    {
        $training = DB::table('trainings')->find($id);
        if (!$training) {
            return response()->json(['message' => 'Non trouvé'], 404);
        }
        return response()->json(['data' => $training]);
    }

    public function updateProgress(Request $request, string $id)
    {
        $v = $request->validate([
            'progress' => 'required|numeric|min:0|max:100',
            'status'   => 'required|in:not_started,in_progress,completed',
        ]);

        $user = $request->user();

        DB::table('training_progress')->updateOrInsert(
            ['training_id' => $id, 'user_id' => $user->id],
            ['progress' => $v['progress'], 'status' => $v['status'], 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['message' => 'Progression mise à jour']);
    }
}
