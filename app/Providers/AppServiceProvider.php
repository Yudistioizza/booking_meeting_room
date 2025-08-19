<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CrudService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CrudService::class, fn ($app) => new CrudService(User::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
