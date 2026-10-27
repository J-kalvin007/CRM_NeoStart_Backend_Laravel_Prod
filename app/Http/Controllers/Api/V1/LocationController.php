<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Suivi GPS des délégués.
 * - Le téléphone du délégué envoie sa position (unitaire ou par lot après une coupure réseau).
 * - Le superviseur consulte le trajet du jour d'un délégué de son équipe.
 */
class LocationController extends Controller
{
    /**
     * POST /me/location
     * Corps : { lat, lng, accuracy?, speed?, battery?, recorded_at? }
     *    ou : { points: [ {lat, lng, accuracy?, speed?, battery?, recorded_at?}, ... ] }
     */
    public function store(Request $request)
    {
        $rules = [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'battery' => 'nullable|integer|between:0,100',
            'recorded_at' => 'nullable|date',
        ];

        if ($request->has('points')) {
            $request->validate(['points' => 'required|array|min:1|max:500']);
            $points = collect($request->input('points'))
                ->map(fn ($p) => validator((array) $p, $rules)->validate());
        } else {
            $points = collect([$request->validate($rules)]);
        }

        $user = $request->user();
        $now = now();

        $rows = $points->map(fn ($p) => [
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'user_id' => $user->id,
            'lat' => $p['lat'],
            'lng' => $p['lng'],
            'accuracy' => $p['accuracy'] ?? null,
            'speed' => $p['speed'] ?? null,
            'battery' => $p['battery'] ?? null,
            // Un horodatage dans le futur (horloge du téléphone décalée) est ramené à maintenant
            'recorded_at' => isset($p['recorded_at']) ? min(Carbon::parse($p['recorded_at']), $now) : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        UserLocation::insert($rows->all());

        // Dernière position connue = point le plus récent reçu (s'il est plus récent que l'actuel)
        $latest = $rows->sortByDesc('recorded_at')->first();
        if (!$user->last_location_at || Carbon::parse($latest['recorded_at'])->gte($user->last_location_at)) {
            $user->forceFill([
                'last_lat' => $latest['lat'],
                'last_lng' => $latest['lng'],
                'last_accuracy' => $latest['accuracy'],
                'last_location_at' => $latest['recorded_at'],
            ])->saveQuietly();
        }

        return response()->json(['message' => 'Position enregistrée.', 'count' => $rows->count()], 201);
    }

    /**
     * GET /users/{id}/locations?date=YYYY-MM-DD
     * Trajet d'un délégué sur une journée (réservé à sa hiérarchie ou à lui-même).
     */
    public function history(Request $request, string $id)
    {
        $user = $request->user();
        $isGlobal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        abort_unless($isGlobal || $id === $user->id || $user->manages($id), 403, "Ce délégué ne fait pas partie de votre équipe.");

        $date = Carbon::parse($request->query('date', now()->toDateString()));

        $delegate = User::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->findOrFail($id, ['id', 'name', 'last_lat', 'last_lng', 'last_location_at']);

        $points = UserLocation::where('user_id', $id)
            ->whereBetween('recorded_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('recorded_at')
            ->get(['lat', 'lng', 'accuracy', 'speed', 'battery', 'recorded_at']);



        return response()->json([
            'delegate' => $delegate,
            'date' => $date->toDateString(),
            'points' => $points,
        ]);
    }
}
