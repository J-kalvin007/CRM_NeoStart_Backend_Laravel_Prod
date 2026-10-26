<?php

namespace App\Support;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** A selected country narrows an already authorized query; it never grants access. */
class CountryFilter
{
    public static function apply(Builder $query, ?string $value = null): Builder
    {
        $value ??= request()->header('X-Country-Id') ?? request()->query('country_id');
        if (!$value || in_array($value, ['group', 'default'], true)) {
            return $query;
        }

        $cache = request()->attributes->get('crm.filter_countries', []);
        if (!array_key_exists($value, $cache)) {
            $cache[$value] = Country::query()->where(fn ($country) => $country
                ->where('id', $value)->orWhere('name', $value)->orWhere('iso_code', $value))->value('id');
            request()->attributes->set('crm.filter_countries', $cache);
        }
        $countryId = $cache[$value];
        if (!$countryId) {
            return $query->whereRaw('1 = 0');
        }

        $model = $query->getModel();
        $table = $model->getTable();
        if ($table === 'products') {
            return $query->whereHas('countries', fn ($country) => $country->where('countries.id', $countryId));
        }
        if ($table === 'coaching_sessions') {
            return $query->where(fn ($sessions) => $sessions
                ->whereHas('evaluated', fn ($user) => $user->where('users.country_id', $countryId))
                ->orWhere(fn ($legacy) => $legacy->whereNull('evaluated_id')
                    ->whereHas('delegate', fn ($user) => $user->where('users.country_id', $countryId))));
        }
        if (Schema::hasColumn($table, 'country_id')) {
            if (method_exists($model, 'user')) {
                return $query->where(fn ($records) => $records->where($table . '.country_id', $countryId)
                    ->orWhere(fn ($owned) => $owned->whereNull($table . '.country_id')
                        ->whereHas('user', fn ($user) => $user
                            ->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                            ->where('users.country_id', $countryId))));
            }
            return $query->where($table . '.country_id', $countryId);
        }
        if (method_exists($model, 'user')) {
            return $query->whereHas('user', fn ($user) => $user
                ->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                ->where('users.country_id', $countryId));
        }
        return $query;
    }
}
