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

        // Register ToolRegistryManager as singleton
        $this->app->singleton(\App\Services\ToolRegistryManager::class, function ($app) {
            return new \App\Services\ToolRegistryManager($app->make(\App\Services\PersistentMcpManager::class));
        });

        // Register ResourceManager as singleton
        $this->app->singleton(\App\Services\ResourceManager::class, function ($app) {
            return new \App\Services\ResourceManager;
        });

        // Register ConversationManager as singleton
        $this->app->singleton(\App\Services\ConversationManager::class, function ($app) {
            return new \App\Services\ConversationManager;
        });

        // Register PromptTemplateManager as singleton
        $this->app->singleton(\App\Services\PromptTemplateManager::class, function ($app) {
            return new \App\Services\PromptTemplateManager;
        });

        // Register AnalyticsManager as singleton
        $this->app->singleton(\App\Services\AnalyticsManager::class, function ($app) {
            return new \App\Services\AnalyticsManager;
        });

        // Register ImportExportManager as singleton
        $this->app->singleton(\App\Services\ImportExportManager::class, function ($app) {
            return new \App\Services\ImportExportManager;
        });

        // Register McpHealthCheckService as singleton
        $this->app->singleton(\App\Services\McpHealthCheckService::class, function ($app) {
            return new \App\Services\McpHealthCheckService;
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
