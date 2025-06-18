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

        // Register BidirectionalMcpClient as singleton
        $this->app->singleton(\App\Services\BidirectionalMcpClient::class, function ($app) {
            return new \App\Services\BidirectionalMcpClient;
        });

        // Register ProcessManager as singleton
        $this->app->singleton(\App\Services\ProcessManager::class, function ($app) {
            return new \App\Services\ProcessManager;
        });

        // Register McpProcessOrchestrator as singleton
        $this->app->singleton(\App\Services\McpProcessOrchestrator::class, function ($app) {
            return new \App\Services\McpProcessOrchestrator;
        });

        // Register McpHealthMonitorService as singleton
        $this->app->singleton(\App\Services\McpHealthMonitorService::class, function ($app) {
            return new \App\Services\McpHealthMonitorService(
                $app->make(\App\Services\McpProcessOrchestrator::class)
            );
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
