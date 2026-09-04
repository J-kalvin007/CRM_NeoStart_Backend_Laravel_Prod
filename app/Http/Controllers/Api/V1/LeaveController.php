<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DB::table('leaves')->orderByDesc('created_at');

        // Manager sees team + own; delegate sees own only
        if (method_exists($user, 'hasRole') && ($user->hasRole('manager') || $user->hasRole('supervisor'))) {
            $teamIds = DB::table('users')->where('manager_id', $user->id)->pluck('id')->toArray();
            $teamIds[] = $user->id;
            $query->whereIn('user_id', $teamIds);
        } else {
            $query->where('user_id', $user->id);
        }

        $leaves = $query->get()->map(fn($l) => [
            'id'         => $l->id,
            'user_id'    => $l->user_id,
            'type'       => $l->type ?? 'Congé annuel',
            'start_date' => $l->start_date,
            'end_date'   => $l->end_date,
            'days'       => $l->days ?? 0,
            'reason'     => $l->reason ?? '',
            'status'     => $l->status ?? 'pending',
            'created_at' => $l->created_at,
        ]);

        return response()->json(['data' => $leaves]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'type'       => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:500',
        ]);

        $days = Carbon::parse($v['start_date'])->diffInWeekdays(Carbon::parse($v['end_date'])) + 1;
        $id   = Str::uuid()->toString();

        DB::table('leaves')->insert([
            'id'         => $id,
            'user_id'    => $request->user()->id,
            'type'       => $v['type'],
            'start_date' => $v['start_date'],
            'end_date'   => $v['end_date'],
            'days'       => $days,
            'reason'     => $v['reason'] ?? '',
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => DB::table('leaves')->find($id)], 201);
    }

    public function updateStatus(Request $request, string $id)
    {
        $v = $request->validate(['status' => 'required|in:approved,rejected,pending']);
        DB::table('leaves')->where('id', $id)->update(['status' => $v['status'], 'updated_at' => now()]);
        return response()->json(['data' => DB::table('leaves')->find($id)]);
    }
}
