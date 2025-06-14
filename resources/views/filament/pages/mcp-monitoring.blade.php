<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Connection Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $this->connectionStats['total'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Connections</div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                        {{ $this->connectionStats['active'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Active</div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                        {{ $this->connectionStats['inactive'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Inactive</div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                        {{ $this->connectionStats['error'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Errors</div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                        {{ $this->connectionStats['connecting'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Connecting</div>
                </div>
            </div>
        </div>

        <!-- System Metrics -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        System Metrics
                    </h3>
                    <x-filament::badge color="success">
                        {{ $this->systemMetrics['manager_status'] ?? 'unknown' }}
                    </x-filament::badge>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['total_requests'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Requests</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['average_response_time'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Avg Response</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['error_rate'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Error Rate</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['uptime'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Uptime</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['memory_usage'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Memory</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->systemMetrics['connections_pool_size'] ?? 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pool Size</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Connections -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Active Connections
                    </h3>
                    <x-filament::button
                        wire:click="loadActiveConnections"
                        icon="heroicon-o-arrow-path"
                        color="gray"
                        size="sm"
                    >
                        Refresh
                    </x-filament::button>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                @if(empty($this->activeConnections))
                    <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                        <x-filament::icon
                            icon="heroicon-o-signal-slash"
                            class="mx-auto h-12 w-12 text-gray-400"
                        />
                        <p class="mt-2">No active connections</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($this->activeConnections as $connection)
                            <div class="rounded-lg border border-gray-300 dark:border-gray-600 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0">
                                            @if($connection['is_manager_active'])
                                                <div class="h-3 w-3 rounded-full bg-success-500"></div>
                                            @else
                                                <div class="h-3 w-3 rounded-full bg-warning-500"></div>
                                            @endif
                                        </div>
                                        
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ $connection['name'] }}
                                            </h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $connection['endpoint_url'] }} 
                                                <span class="text-xs">
                                                    ({{ $connection['transport_type'] }})
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <x-filament::badge 
                                            :color="$connection['status'] === 'active' ? 'success' : 'warning'"
                                        >
                                            {{ $connection['status'] }}
                                        </x-filament::badge>
                                        
                                        <div class="flex gap-1">
                                            <x-filament::button
                                                wire:click="refreshConnection({{ $connection['id'] }})"
                                                icon="heroicon-o-arrow-path"
                                                color="primary"
                                                size="xs"
                                                tooltip="Refresh Connection"
                                            />
                                            
                                            @if($connection['is_manager_active'])
                                                <x-filament::button
                                                    wire:click="stopConnection({{ $connection['id'] }})"
                                                    icon="heroicon-o-stop"
                                                    color="warning"
                                                    size="xs"
                                                    tooltip="Stop Connection"
                                                />
                                            @else
                                                <x-filament::button
                                                    wire:click="startConnection({{ $connection['id'] }})"
                                                    icon="heroicon-o-play"
                                                    color="success"
                                                    size="xs"
                                                    tooltip="Start Connection"
                                                />
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    <div class="flex justify-between">
                                        <span>Created: {{ \Carbon\Carbon::parse($connection['created_at'])->diffForHumans() }}</span>
                                        <span>Updated: {{ \Carbon\Carbon::parse($connection['updated_at'])->diffForHumans() }}</span>
                                    </div>
                                </div>
                                
                                @if(!empty($connection['capabilities']))
                                    <div class="mt-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($connection['capabilities'] as $capability => $enabled)
                                                @if($enabled)
                                                    <x-filament::badge color="info" size="xs">
                                                        {{ $capability }}
                                                    </x-filament::badge>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh connections every 30 seconds
            setInterval(function() {
                @this.call('loadActiveConnections');
                @this.call('loadConnectionStats');
                @this.call('loadSystemMetrics');
            }, 30000);

            // Listen for real-time updates
            window.addEventListener('connection-status-updated', function() {
                console.log('Connection status updated via real-time event');
            });

            window.addEventListener('metrics-updated', function() {
                console.log('System metrics updated via real-time event');
            });

            // Echo listeners for real-time updates
            if (window.Echo) {
                window.Echo.channel('mcp-connections')
                    .listen('.connection.status.changed', (e) => {
                        console.log('Connection status changed:', e);
                        @this.call('handleConnectionStatusChanged', e);
                    });

                window.Echo.channel('mcp-server')
                    .listen('.server.metrics.updated', (e) => {
                        console.log('Server metrics updated:', e);
                        @this.call('handleServerMetricsUpdated', e);
                    });
            }
        });
    </script>
</x-filament-panels::page>