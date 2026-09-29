<?php

namespace App\Models\Traits;

use App\Models\Scopes\DynamicPerimeterScope;
use App\Services\PerimeterService;
use Illuminate\Support\Facades\Auth;

trait HasDynamicPerimeter
{
    use HasPerimeter;

    /**
     * Get the perimeter service instance
     */
    public static function getPerimeterService(): PerimeterService
    {
        return app(PerimeterService::class);
    }

    /**
     * Scope query to user's accessible organizational units
     */
    public function scopeWithinUserPerimeter($query, $user = null)
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return $query;
        }

        $perimeterService = self::getPerimeterService();
        $accessibleUnitIds = $perimeterService->getAccessibleUnits($user)->pluck('id')->toArray();

        if (empty($accessibleUnitIds)) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereIn('user_id', function ($subQuery) use ($accessibleUnitIds) {
            $subQuery->select('user_id')
                     ->from('user_scopes')
                     ->whereIn('organizational_unit_id', $accessibleUnitIds)
                     ->where('is_active', true);
        });
    }

    /**
     * Scope query to specific organizational unit and descendants
     */
    public function scopeWithinUnit($query, string $unitId)
    {
        $perimeterService = self::getPerimeterService();
        $accessibleIds = $perimeterService->getDescendantUnitIds([$unitId]);

        return $query->whereIn('user_id', function ($subQuery) use ($accessibleIds) {
            $subQuery->select('user_id')
                     ->from('user_scopes')
                     ->whereIn('organizational_unit_id', $accessibleIds)
                     ->where('is_active', true);
        });
    }

    /**
     * Check if current user can access this record
     */
    public function isAccessibleByUser($user = null): bool
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return false;
        }

        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        // If record belongs to the user
        if (isset($this->user_id) && $this->user_id === $user->id) {
            return true;
        }

        // Check through organizational units
        if (isset($this->user_id)) {
            $recordUserScopes = \App\Models\UserScope::where('user_id', $this->user_id)
                ->where('is_active', true)
                ->pluck('organizational_unit_id')
                ->toArray();

            if (empty($recordUserScopes)) {
                return false;
            }

            $perimeterService = self::getPerimeterService();
            $userAccessibleIds = $perimeterService->getAccessibleUnits($user)->pluck('id')->toArray();

            return !empty(array_intersect($recordUserScopes, $userAccessibleIds));
        }

        return false;
    }

    /**
     * Get records accessible by user without applying global scope
     */
    public static function accessibleBy($user = null)
    {
        return static::withoutGlobalScope(DynamicPerimeterScope::class)
                     ->withinUserPerimeter($user);
    }

    /**
     * Get all records within a specific organizational unit
     */
    public static function withinOrganizationalUnit(string $unitId)
    {
        return static::withoutGlobalScope(DynamicPerimeterScope::class)
                     ->withinUnit($unitId);
    }

    /**
     * Boot the trait
     */
    protected static function bootHasDynamicPerimeter()
    {
        // Add any additional boot logic here if needed
    }
}