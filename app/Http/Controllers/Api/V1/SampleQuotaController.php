<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleQuota;
use Illuminate\Http\Request;

class SampleQuotaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = SampleQuota::with(['product', 'user'])
            ->orderBy('period_end', 'desc');

        if (!($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']))) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'product_id' => 'required|uuid|exists:products,id',
            'quota' => 'required|integer|min:1',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $quota = SampleQuota::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'product_id' => $validated['product_id'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
            ],
            ['quota' => $validated['quota']]
        );

        return response()->json($quota->load(['product', 'user']), 201);
    }

    private function authorizeAdmin($user)
    {
        abort_unless($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']), 403, 'Seul un administrateur peut gérer les quotas.');
    }
}
