<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DelegateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    private function mapLeave(DelegateRequest $leave): array
    {
        $payload = [];
        if (!empty($leave->reason)) {
            $decoded = json_decode($leave->reason, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $user = User::find($leave->user_id);

        $status = match ($leave->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'pending',
        };

        return [
            'id' => (string) $leave->id,
            'user_id' => (string) $leave->user_id,
            'employee_name' => $user?->name ?? 'Délégué',
            'type' => $payload['type'] ?? 'Congé Annuel',
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
            'days' => (int) ($payload['days'] ?? 0),
            'reason' => $payload['reason'] ?? '',
            'status' => $status,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = DelegateRequest::where('request_type', 'leave')->latest();

        if (!$user || !$user->hasAnyRole(['super-admin', 'admin-pays', 'dir-general', 'dir-commercial', 'manager'])) {
            $query->where('user_id', Auth::id());
        }

        return response()->json([
            'data' => $query->get()->map(fn (DelegateRequest $leave) => $this->mapLeave($leave))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
            'status' => 'nullable|string',
            'local_uuid' => 'nullable|uuid',
        ]);

        $leave = DelegateRequest::create([
            'user_id' => Auth::id(),
            'request_type' => 'leave',
            'reason' => json_encode([
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days' => $data['days'],
                'reason' => $data['reason'],
            ]),
            'priority' => 'normal',
            'status' => 'pending_manager_approval',
            'local_uuid' => $data['local_uuid'] ?? null,
        ]);

        return response()->json([
            'data' => $this->mapLeave($leave),
        ], 201);
    }

    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,pending',
        ]);

        $leave = DelegateRequest::where('request_type', 'leave')->findOrFail($id);
        $leave->update([
            'status' => match ($data['status']) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'pending_manager_approval',
            },
        ]);

        return response()->json([
            'data' => $this->mapLeave($leave),
        ]);
    }
}