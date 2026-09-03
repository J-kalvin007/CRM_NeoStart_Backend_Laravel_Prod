<?php

namespace App\Models\Traits;

use App\Models\Scopes\PerimeterScope;

trait HasPerimeter
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new PerimeterScope);
    }
}
