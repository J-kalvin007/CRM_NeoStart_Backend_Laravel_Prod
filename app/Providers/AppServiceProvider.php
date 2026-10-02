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
use App\Models\Task;
use App\Observers\TaskObserver;
use App\Models\Region;
use App\Observers\RegionObserver;
use App\Models\Territory;
use App\Observers\TerritoryObserver;
use App\Models\Zone;
use App\Observers\ZoneObserver;
use App\Models\Sector;
use App\Observers\SectorObserver;

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
        Task::observe(TaskObserver::class);
        
        Region::observe(RegionObserver::class);
        Territory::observe(TerritoryObserver::class);
        Zone::observe(ZoneObserver::class);
        Sector::observe(SectorObserver::class);
    }
}
