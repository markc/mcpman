<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PersistentMcpManager as singleton
        $this->app->singleton(\App\Services\PersistentMcpManager::class, function ($app) {
            return new \App\Services\PersistentMcpManager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
