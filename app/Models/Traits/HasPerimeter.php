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
     * Get all accessible instances for the current user
     */
    public static function accessible($user = null)
    {
        return static::all(); // Simplified, scope applies automatically
    }
}
