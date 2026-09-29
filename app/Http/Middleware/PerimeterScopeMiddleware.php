<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\OrganizationalUnit;
use App\Models\UserScope;
use App\Models\Scopes\DynamicPerimeterScope;

/**
 * Middleware PerimeterScopeMiddleware
 *
 * Lit le header X-Organizational-Unit-Id envoyé par le frontend
 * et le stocke dans le singleton d'application pour que les scopes
 * et contrôleurs puissent l'utiliser pour filtrer les données.
 *
 * Le filtrage n'est appliqué que si :
 *   1. L'utilisateur est authentifié
 *   2. L'unité demandée est dans le périmètre accessible de l'utilisateur
 */
class PerimeterScopeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $unitId = $request->header('X-Organizational-Unit-Id');

        if ($unitId && Auth::hasUser()) {
            $user = Auth::user();

            // Les admins peuvent voir tout le périmètre sans restriction
            $isAdmin = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin']);

            if ($isAdmin || $this->userCanAccessUnit($user, $unitId)) {
                // Stocker l'ID dans le conteneur d'application pour usage dans les contrôleurs/scopes
                app()->instance('active_perimeter_unit_id', $unitId);

                // Aussi disponible via request
                $request->merge(['_perimeter_unit_id' => $unitId]);
            }
        }

        return $next($request);
    }

    /**
     * Vérifie si l'utilisateur a accès à l'unité organisationnelle demandée
     */
    protected function userCanAccessUnit($user, string $unitId): bool
    {
        // Récupérer les scopes actifs de l'utilisateur
        $userScopeIds = UserScope::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('organizational_unit_id')
            ->toArray();

        if (empty($userScopeIds)) {
            return false;
        }

        // Récupérer tous les descendants des scopes de l'utilisateur
        $scope = new DynamicPerimeterScope();
        $accessibleIds = $this->getDescendantUnitIds($userScopeIds);

        return in_array($unitId, $accessibleIds);
    }

    /**
     * Récupère tous les IDs descendants récursivement
     */
    protected function getDescendantUnitIds(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $allIds = $parentIds;
        $currentParentIds = $parentIds;

        while (true) {
            $childrenIds = \Illuminate\Support\Facades\DB::table('organizational_units')
                ->whereIn('parent_id', $currentParentIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (empty($childrenIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childrenIds);
            $currentParentIds = $childrenIds;
        }

        return array_unique($allIds);
    }
}
