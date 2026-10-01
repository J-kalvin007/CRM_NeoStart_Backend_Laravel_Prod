<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Données de référence (paramètres, catalogues, organisation, rôles…) :
 * lecture pour tout utilisateur connecté, écriture réservée à l'administration et à la direction.
 */
class RestrictReferenceWrites
{
    /** Préfixes d'URI (après api/v1/) considérés comme données de référence */
    private const REFERENCE_PREFIXES = [
        'config/', 'settings', 'branding',
        'specialities', 'segmentations', 'potentials', 'influences', 'galenic-forms',
        'activity-types', 'activity-results', 'event-formats', 'objectif-types',
        'categories', 'laboratories', 'therapeutic-classes', 'products', 'digital-supports', 'custom-fields',
        'countries', 'subsidiaries', 'regions', 'territories', 'zones', 'sectors', 'teams',
        'roles', 'permissions', 'organization/', 'hierarchy/',
    ];

    /** Rôles autorisés à modifier les référentiels */
    private const MANAGER_ROLES = [
        'superadmin', 'super-admin', 'admin',
        'directeur_commercial', 'directeur-commercial',
        'directeur_marketing', 'directeur-marketing',
        'directeur_regional', 'directeur-regional',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || !$request->user()) {
            return $next($request);
        }

        $path = preg_replace('#^api/v1/#', '', $request->path());
        foreach (self::REFERENCE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                if (!$request->user()->hasAnyRole(self::MANAGER_ROLES) && !$request->user()->can('scope:global')) {
                    return response()->json([
                        'message' => "Modification réservée à l'administration et à la direction.",
                    ], 403);
                }
                break;
            }
        }

        return $next($request);
    }
}
