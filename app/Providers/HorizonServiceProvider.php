<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // ── Notification Channels (optional) ──
        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * In local environment, access is granted automatically.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            // Grant access to admin users (based on the user role stored
            // in the `role` column — matching the existing auth system)
            return $user && $user->role === 'ADMIN';
        });
    }
}
