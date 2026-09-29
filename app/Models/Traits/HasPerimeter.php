<?php

namespace App\Models\Traits;

use App\Models\Scopes\DynamicPerimeterScope;
use App\Services\PerimeterService;

trait HasPerimeter
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new DynamicPerimeterScope);
    }

    /**
     * Get the perimeter service instance
     */
    public static function getPerimeterService(): PerimeterService
    {
        return app(PerimeterService::class);
    }

    /**
     * Check if current user can access this model instance
     */
    public function canBeAccessedByUser($user = null): bool
    {
        return DynamicPerimeterScope::userCanAccessUnit(
            $user ?? auth()->user(), 
            $this->getKey()
        );
    }

    /**
     * Get all accessible instances for the current user
     */
    public static function accessible($user = null)
    {
        return DynamicPerimeterScope::getAccessibleUnits(
            $user ?? auth()->user()
        );
    }
}
