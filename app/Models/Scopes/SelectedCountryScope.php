<?php

namespace App\Models\Scopes;

use App\Support\CountryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/** Country selection remains active when a controller supplies its own hierarchy rules. */
class SelectedCountryScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::hasUser()) {
            CountryFilter::apply($builder);
        }
    }
}
