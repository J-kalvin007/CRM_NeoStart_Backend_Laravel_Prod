<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComplianceFinding;
use App\Models\Setting;
use App\Services\ComplianceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    public function index(Request $request, ComplianceReview $review)
    {
        $review->detect($request->user());
        $rows = ComplianceFinding::whereIn('user_id', $review->userIds($request->user()))->with(['user', 'decisions.actor'])->latest('detected_at')->get()->map(fn ($row) => [
            'id' => $row->id, 'activity_id' => $row->activity_id, 'user_id' => $row->user_id, 'type' => $row->type,
            'collaborateur' => $row->user?->name ?? 'Collaborateur', 'description' => $row->description,
            'details' => json_encode($row->evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'evidence' => $row->evidence, 'niveau' => $row->severity, 'date_detection' => $row->detected_at->toISOString(),
            'statut' => $row->status, 'can_respond' => $row->user_id === $request->user()->id && $row->status === 'EXPLICATION_DEMANDEE', 'can_decide' => $this->canDecide($request->user(), $row),
            'decisions' => $row->decisions->map(fn ($decision) => ['action' => $decision->action, 'note' => $decision->note,
                'actor_name' => $decision->actor?->name, 'date' => $decision->created_at->toISOString()]),
        ]);

        return response()->json($rows);
    }

    public function decide(Request $request, string $id, string $action, ComplianceReview $review)
    {
        abort_unless(in_array($action, ['resolve', 'request-explanation', 'reopen', 'respond']), 404);
        $row = ComplianceFinding::whereIn('user_id', $review->userIds($request->user()))->findOrFail($id);
        abort_unless($action === 'respond' ? ($row->user_id === $request->user()->id && $row->status === 'EXPLICATION_DEMANDEE') : $this->canDecide($request->user(), $row), 403);
        $validated = $request->validate(['note' => 'required|string|min:3|max:4000']);
        DB::transaction(function () use ($row, $action, $validated, $request) {
            $row = ComplianceFinding::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_if($action === 'respond' && $row->status !== 'EXPLICATION_DEMANDEE', 409, 'Cette demande a déjà été traitée.');
            $row->update(['status' => match ($action) {
                'resolve' => 'RESOLU', 'reopen', 'respond' => 'EN_ATTENTE', default => 'EXPLICATION_DEMANDEE'
            }]);
            $row->decisions()->create(['actor_id' => $request->user()->id, 'action' => $action, 'note' => $validated['note']]);
        });
        if ($action === 'request-explanation') {
            try {
                NotificationController::sendNotification($row->user, 'Explication demandée', $validated['note'], 'compliance', 'all', ['finding_id' => $row->id]);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        return response()->json(['message' => 'Décision enregistrée.', 'status' => $row->fresh()->status]);
    }

    public function rules(ComplianceReview $review)
    {
        return response()->json($review->rules());
    }

    public function updateRules(Request $request, ComplianceReview $review)
    {
        abort_unless($request->user()->isAdminOrDirection(), 403);
        $values = $request->validate(['short_visit_minutes' => 'present|nullable|numeric|gt:0|max:1440', 'gps_tolerance_meters' => 'present|nullable|numeric|gt:0|max:100000']);
        DB::transaction(function () use ($values, $request) {
            foreach ($values as $key => $value) {
                $name = 'compliance_'.$key;
                if ($value === null) {
                    Setting::where('key', $name)->delete();
                } else {
                    Setting::updateOrCreate(['key' => $name], ['value' => (string) $value]);
                }
            }
            activity()->causedBy($request->user())->withProperties($values)->log('Mise à jour des seuils de conformité');
        });

        return response()->json($review->rules());
    }

    private function canDecide($viewer, ComplianceFinding $finding): bool
    {
        return $viewer->isAdminOrDirection() || ($viewer->id !== $finding->user_id && $viewer->manages($finding->user_id));
    }
}
