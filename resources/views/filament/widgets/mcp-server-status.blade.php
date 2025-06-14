<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            MCP Server Status
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::button
                wire:click="refreshStatus"
                icon="heroicon-o-arrow-path"
                color="gray"
                size="sm"
            >
                Refresh
            </x-filament::button>
        </x-slot>

        <div class="space-y-4">
            <!-- Server Status -->
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</span>
                <div class="flex items-center gap-2">
                    @if(($this->serverStatus['status'] ?? 'unknown') === 'running')
                        <div class="h-2 w-2 rounded-full bg-success-500"></div>
                        <x-filament::badge color="success">Running</x-filament::badge>
                    @elseif(($this->serverStatus['status'] ?? 'unknown') === 'error')
                        <div class="h-2 w-2 rounded-full bg-danger-500"></div>
                        <x-filament::badge color="danger">Error</x-filament::badge>
                    @else
                        <div class="h-2 w-2 rounded-full bg-warning-500"></div>
                        <x-filament::badge color="warning">Unknown</x-filament::badge>
                    @endif
                </div>
            </div>

            <!-- Uptime -->
            @if(isset($this->serverStatus['uptime']))
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Uptime</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->serverStatus['uptime'] }}
                    </span>
                </div>
            @endif

            <!-- Memory Usage -->
            @if(isset($this->serverStatus['memory_usage']))
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Memory</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ round($this->serverStatus['memory_usage'] / 1024 / 1024, 1) }}MB
                        / {{ round($this->serverStatus['memory_peak'] / 1024 / 1024, 1) }}MB peak
                    </span>
                </div>
            @endif

            <!-- PHP Version -->
            @if(isset($this->serverStatus['php_version']))
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">PHP</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->serverStatus['php_version'] }}
                    </span>
                </div>
            @endif

            <!-- Laravel Version -->
            @if(isset($this->serverStatus['laravel_version']))
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Laravel</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->serverStatus['laravel_version'] }}
                    </span>
                </div>
            @endif

            <!-- Active Connections -->
            @if(isset($this->serverStatus['active_connections']))
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Connections</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->serverStatus['active_connections'] }}
                    </span>
                </div>
            @endif

            <!-- Health Check Results -->
            @if(isset($this->serverStatus['health_check']) && is_array($this->serverStatus['health_check']) && !empty($this->serverStatus['health_check']))
                <div class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Connection Health</span>
                    <div class="space-y-1">
                        @foreach($this->serverStatus['health_check'] as $connectionName => $result)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-600 dark:text-gray-400">{{ $connectionName }}</span>
                                @if($result === true)
                                    <x-filament::badge color="success" size="xs">Healthy</x-filament::badge>
                                @else
                                    <x-filament::badge color="danger" size="xs">Unhealthy</x-filament::badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif(isset($this->serverStatus['health_check']) && empty($this->serverStatus['health_check']))
                <div class="space-y-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Connection Health</span>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        No active connections to check
                    </div>
                </div>
            @endif

            <!-- Error Details -->
            @if(isset($this->serverStatus['error']))
                <div class="space-y-2">
                    <span class="text-sm font-medium text-danger-700 dark:text-danger-300">Error</span>
                    <div class="text-xs text-danger-600 dark:text-danger-400 bg-danger-50 dark:bg-danger-950 rounded p-2">
                        {{ $this->serverStatus['error'] }}
                    </div>
                </div>
            @endif

            <!-- Last Updated -->
            @if(isset($this->serverStatus['last_updated']))
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 border-t pt-2">
                    <span>Last Updated</span>
                    <span>{{ \Carbon\Carbon::parse($this->serverStatus['last_updated'])->diffForHumans() }}</span>
                </div>
            @endif
        </div>
    </x-filament::section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh server status every 60 seconds
            setInterval(function() {
                @this.call('loadServerStatus');
            }, 60000);

            // Listen for real-time updates
            window.addEventListener('server-status-updated', function() {
                console.log('Server status updated via real-time event');
            });

            // Echo listener for real-time server status updates
            if (window.Echo) {
                window.Echo.channel('mcp-server')
                    .listen('.server.status.changed', (e) => {
                        console.log('Server status changed:', e);
                        @this.call('handleServerStatusChanged', e);
                    });
            }
        });
    </script>
</x-filament-widgets::widget>