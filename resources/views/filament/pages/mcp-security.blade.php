<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Security Settings Form -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Security Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                        {{ \App\Models\McpConnection::where('status', 'active')->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Secure Connections</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Active and authenticated
                    </div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                        {{ \App\Models\McpConnection::where('transport_type', 'http')->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">HTTP Connections</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        May need SSL review
                    </div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                        {{ \App\Models\McpConnection::where('status', 'error')->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Error State</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Require attention
                    </div>
                </div>
            </div>
            
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content px-6 py-4 text-center">
                    <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                        {{ \App\Models\User::count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Users</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        With MCP access
                    </div>
                </div>
            </div>
        </div>

        <!-- Connection Security Table -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Connection Security Status
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content">
                {{ $this->table }}
            </div>
        </div>

        <!-- Security Recommendations -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Security Recommendations
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-blue-50 dark:bg-blue-950">
                        <x-filament::icon
                            icon="heroicon-o-information-circle"
                            class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5"
                        />
                        <div>
                            <h4 class="font-medium text-blue-900 dark:text-blue-100">Enable SSL/TLS</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Always use secure connections (HTTPS/WSS) for MCP communications to protect data in transit.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-amber-50 dark:bg-amber-950">
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5"
                        />
                        <div>
                            <h4 class="font-medium text-amber-900 dark:text-amber-100">Regular Security Audits</h4>
                            <p class="text-sm text-amber-700 dark:text-amber-300">
                                Perform regular security audits to identify vulnerable connections and configurations.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-950">
                        <x-filament::icon
                            icon="heroicon-o-shield-check"
                            class="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5"
                        />
                        <div>
                            <h4 class="font-medium text-green-900 dark:text-green-100">Authentication Required</h4>
                            <p class="text-sm text-green-700 dark:text-green-300">
                                All MCP connections should require proper authentication to prevent unauthorized access.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-purple-50 dark:bg-purple-950">
                        <x-filament::icon
                            icon="heroicon-o-clock"
                            class="h-5 w-5 text-purple-600 dark:text-purple-400 mt-0.5"
                        />
                        <div>
                            <h4 class="font-medium text-purple-900 dark:text-purple-100">Monitor Connection Activity</h4>
                            <p class="text-sm text-purple-700 dark:text-purple-300">
                                Keep logs of all connection attempts and monitor for suspicious activity patterns.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Security Events -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Recent Security Events
                    </h3>
                    <x-filament::badge color="info">
                        Last 24 hours
                    </x-filament::badge>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="space-y-3">
                    <!-- Simulated security events -->
                    <div class="flex items-center gap-3 text-sm">
                        <div class="h-2 w-2 rounded-full bg-green-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ now()->subMinutes(15)->format('H:i') }}</span>
                        <span class="text-gray-900 dark:text-gray-100">Successful connection from user: admin@example.com</span>
                    </div>
                    
                    <div class="flex items-center gap-3 text-sm">
                        <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ now()->subHours(2)->format('H:i') }}</span>
                        <span class="text-gray-900 dark:text-gray-100">Security settings updated</span>
                    </div>
                    
                    <div class="flex items-center gap-3 text-sm">
                        <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ now()->subHours(4)->format('H:i') }}</span>
                        <span class="text-gray-900 dark:text-gray-100">Rate limit warning for user: test@example.com</span>
                    </div>
                    
                    <div class="text-center py-4">
                        <x-filament::button
                            href="#"
                            color="gray"
                            size="sm"
                        >
                            View Full Security Log
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh security status every 5 minutes
            setInterval(function() {
                console.log('Refreshing security status...');
                // In a production app, this would refresh real-time security data
            }, 300000);
        });
    </script>
</x-filament-panels::page>