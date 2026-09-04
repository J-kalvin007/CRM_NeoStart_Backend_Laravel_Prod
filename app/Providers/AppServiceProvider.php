<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
use App\Models\Activity;
use App\Observers\ActivityObserver;
use App\Models\Campaign;
use App\Observers\CampaignObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Activity::observe(ActivityObserver::class);
        Campaign::observe(CampaignObserver::class);
    }
}
