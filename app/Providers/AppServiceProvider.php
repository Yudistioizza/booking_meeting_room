<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Microsoft Entra ID (Azure AD) single sign-on
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        // Role-based access. Role names live in config('booking.roles').
        Gate::define('admin', fn (User $u) => $u->isAdmin());
        Gate::define('approve-bookings', fn (User $u) => $u->isApprover());
        Gate::define('manage-rooms', fn (User $u) => $u->isAdmin());
        Gate::define('manage-users', fn (User $u) => $u->isAdmin());
        Gate::define('view-reports', fn (User $u) => $u->isApprover());
    }
}
