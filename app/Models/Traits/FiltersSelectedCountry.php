<?php

namespace App\Models\Traits;

use App\Models\Scopes\SelectedCountryScope;

trait FiltersSelectedCountry
{
    protected static function bootFiltersSelectedCountry(): void
    {
        static::addGlobalScope(new SelectedCountryScope);
    }
}
