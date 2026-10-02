<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\OrganizationConfig;
use App\Models\UserScope;
use App\Policies\OrganizationConfigPolicy;
use App\Policies\UserScopePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        OrganizationConfig::class => OrganizationConfigPolicy::class,
        UserScope::class => UserScopePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates if needed
        Gate::define('manage-hierarchy', function ($user) {
            return $user->can('scope:global') || 
                   $user->hasAnyRole(['superadmin', 'admin', 'directeur_commercial', 'directeur_regional']);
        });

        Gate::define('manage-user-scopes', function ($user) {
            return app(\App\Services\PerimeterService::class)->canManageUsers($user);
        });

        Gate::define('create-organizational-units', function ($user) {
            return app(\App\Services\PerimeterService::class)->canCreateUnits($user);
        });

        Gate::define('edit-organizational-units', function ($user) {
            return app(\App\Services\PerimeterService::class)->canEditUnits($user);
        });

        Gate::define('delete-organizational-units', function ($user) {
            return app(\App\Services\PerimeterService::class)->canDeleteUnits($user);
        });
    }
}