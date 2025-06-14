<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Configuration Form -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Current Environment Info -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Environment Information
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Laravel Environment</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ app()->environment() }}</div>
                    </div>
                    
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Laravel Version</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ app()->version() }}</div>
                    </div>
                    
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">PHP Version</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ PHP_VERSION }}</div>
                    </div>
                    
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Config Path</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ config_path('mcp.php') }}</div>
                    </div>
                    
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Cache Status</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            @if(app()->configurationIsCached())
                                <x-filament::badge color="warning">Cached</x-filament::badge>
                            @else
                                <x-filament::badge color="success">Not Cached</x-filament::badge>
                            @endif
                        </div>
                    </div>
                    
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Debug Mode</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            @if(config('app.debug'))
                                <x-filament::badge color="warning">Enabled</x-filament::badge>
                            @else
                                <x-filament::badge color="success">Disabled</x-filament::badge>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current MCP Configuration -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Current MCP Configuration
                    </h3>
                    <x-filament::button
                        wire:click="loadConfiguration"
                        icon="heroicon-o-arrow-path"
                        color="gray"
                        size="sm"
                    >
                        Refresh
                    </x-filament::button>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <pre class="text-xs text-gray-700 dark:text-gray-300 overflow-x-auto">{{ json_encode(config('mcp'), JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        </div>

        <!-- Configuration Help -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Configuration Help
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Connection Timeout</h4>
                        <p>Maximum time in milliseconds to wait for MCP connections. Higher values allow for slower connections but may impact performance.</p>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Persistent Connections</h4>
                        <p>When enabled, connections to Claude Code are kept alive for better performance. Disable if you experience connection issues.</p>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Broadcasting</h4>
                        <p>Real-time updates via WebSockets. Requires Laravel Reverb to be running. Disable if not using real-time features.</p>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Rate Limiting</h4>
                        <p>Limits the number of requests per minute per connection. Helps prevent overwhelming Claude Code instances.</p>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-xs">
                            <strong>Note:</strong> Some configuration changes may require restarting the Laravel application or queue workers to take effect.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-save configuration on form changes (with debounce)
            let saveTimeout;
            const form = document.querySelector('form');
            
            if (form) {
                form.addEventListener('change', function() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(function() {
                        console.log('Auto-saving configuration...');
                        // In a production app, you might want to auto-save here
                    }, 2000);
                });
            }
        });
    </script>
</x-filament-panels::page>