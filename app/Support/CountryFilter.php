<?php

namespace App\Support;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** A selected country narrows an already authorized query; it never grants access. */
class CountryFilter
{
    public static function selectedValue(): ?string
    {
        return request()->header('X-Country-Id') ?? request()->query('country_id') ?? request()->query('country') ?? request()->query('pays');
    }

    public static function resolveCountryId(?string $value = null): ?string
    {
        $value ??= self::selectedValue();
        if (!$value || in_array($value, ['group', 'default'], true)) return null;
        $cache = request()->attributes->get('crm.filter_countries', []);
        if (!array_key_exists($value, $cache)) {
            $cache[$value] = Country::query()->where(fn ($country) => $country
                ->where('id', $value)->orWhere('name', $value)->orWhere('iso_code', $value))->value('id');
            request()->attributes->set('crm.filter_countries', $cache);
        }
        return $cache[$value];
    }

    public static function apply(Builder $query, ?string $value = null): Builder
    {
        $value ??= self::selectedValue();
        if (!$value || in_array($value, ['group', 'default'], true)) {
            return $query;
        }

        $countryId = self::resolveCountryId($value);
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
        // Legacy KOL rows have no country or owner: never assign them to a selected country.
        if ($table === 'kols') {
            return $query->whereRaw('1 = 0');
        }
        if ($table === 'digital_supports') {
            return $query->whereHas('product', fn ($product) => self::apply($product, $value));
        }
        if ($table === 'kol_profiles') {
            return $query->whereHas('contact', fn ($contact) => self::apply($contact, $value));
        }
        if (method_exists($model, 'user')) {
            return $query->whereHas('user', fn ($user) => $user
                ->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                ->where('users.country_id', $countryId));
        }
        return $query;
    }
}
