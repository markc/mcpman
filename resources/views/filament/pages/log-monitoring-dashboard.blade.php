<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Monitoring Status Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-filament::card>
                <div class="flex items-center space-x-3">
                    <x-filament::icon 
                        :icon="$this->getStatusIcon($monitoringStats['monitoring_supported'] ?? false)" 
                        :class="'w-8 h-8 text-' . $this->getStatusColor($monitoringStats['monitoring_supported'] ?? false) . '-500'"
                    />
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">System Support</p>
                        <p class="text-lg font-semibold">
                            {{ $monitoringStats['monitoring_supported'] ? 'Available' : 'Unavailable' }}
                        </p>
                    </div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center space-x-3">
                    <x-filament::icon 
                        icon="heroicon-o-puzzle-piece"
                        class="w-8 h-8 text-info-500"
                    />
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Error Patterns</p>
                        <p class="text-lg font-semibold">{{ $monitoringStats['patterns_configured'] ?? 0 }}</p>
                    </div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center space-x-3">
                    <x-filament::icon 
                        :icon="$this->getStatusIcon($monitoringStats['log_file_exists'] ?? false)" 
                        :class="'w-8 h-8 text-' . $this->getStatusColor($monitoringStats['log_file_exists'] ?? false) . '-500'"
                    />
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Log File</p>
                        <p class="text-lg font-semibold">
                            {{ $monitoringStats['log_file_exists'] ? 'Found' : 'Missing' }}
                        </p>
                    </div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="flex items-center space-x-3">
                    <x-filament::icon 
                        icon="heroicon-o-chart-bar"
                        class="w-8 h-8 text-primary-500"
                    />
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Log Size</p>
                        <p class="text-lg font-semibold">{{ $this->formatFileSize($monitoringStats['log_file_size'] ?? 0) }}</p>
                    </div>
                </div>
            </x-filament::card>
        </div>

        {{-- How It Works --}}
        <x-filament::card>
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    🤖 Automated Log Monitoring System
                </h3>
                <div class="prose dark:prose-invert max-w-none">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        The automated log monitoring system continuously watches your Laravel log files and automatically 
                        detects errors, sending detailed context to Claude for immediate analysis and fixes.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <x-filament::icon icon="heroicon-o-eye" class="w-6 h-6 text-blue-500" />
                            </div>
                            <h4 class="font-medium mb-2">Real-time Detection</h4>
                            <p class="text-xs text-gray-500">Monitors log files using tail -f for instant error detection</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <x-filament::icon icon="heroicon-o-cpu-chip" class="w-6 h-6 text-amber-500" />
                            </div>
                            <h4 class="font-medium mb-2">Smart Analysis</h4>
                            <p class="text-xs text-gray-500">Classifies errors and gathers file context automatically</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="w-6 h-6 text-green-500" />
                            </div>
                            <h4 class="font-medium mb-2">Auto-fix Suggestions</h4>
                            <p class="text-xs text-gray-500">Provides Claude with context for immediate fixes</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::card>

        {{-- Error Types Detected --}}
        <x-filament::card>
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    🔍 Error Types Detected
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php
                    $errorTypes = [
                        ['name' => 'PHP Fatal Errors', 'severity' => 'critical', 'auto_fix' => true, 'icon' => 'heroicon-o-exclamation-triangle'],
                        ['name' => 'Laravel Exceptions', 'severity' => 'high', 'auto_fix' => true, 'icon' => 'heroicon-o-bug-ant'],
                        ['name' => 'Syntax Errors', 'severity' => 'critical', 'auto_fix' => true, 'icon' => 'heroicon-o-code-bracket'],
                        ['name' => 'Class Not Found', 'severity' => 'high', 'auto_fix' => true, 'icon' => 'heroicon-o-question-mark-circle'],
                        ['name' => 'Method Not Found', 'severity' => 'high', 'auto_fix' => true, 'icon' => 'heroicon-o-x-circle'],
                        ['name' => 'Duplicate Methods', 'severity' => 'critical', 'auto_fix' => true, 'icon' => 'heroicon-o-document-duplicate'],
                    ];
                    @endphp

                    @foreach($errorTypes as $error)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <div class="flex items-start space-x-3">
                                <x-filament::icon 
                                    :icon="$error['icon']" 
                                    class="w-5 h-5 text-{{ $error['severity'] === 'critical' ? 'danger' : 'warning' }}-500 mt-0.5 flex-shrink-0"
                                />
                                <div class="flex-1">
                                    <p class="text-sm font-medium">{{ $error['name'] }}</p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $error['severity'] === 'critical' ? 'red' : 'yellow' }}-100 text-{{ $error['severity'] === 'critical' ? 'red' : 'yellow' }}-800 dark:bg-{{ $error['severity'] === 'critical' ? 'red' : 'yellow' }}-900/20 dark:text-{{ $error['severity'] === 'critical' ? 'red' : 'yellow' }}-200">
                                            {{ ucfirst($error['severity']) }}
                                        </span>
                                        @if($error['auto_fix'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200">
                                                Auto-fix
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-filament::card>

        {{-- Quick Start Guide --}}
        <x-filament::card>
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    🚀 Quick Start Guide
                </h3>
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium">1</div>
                        <div>
                            <p class="text-sm font-medium">Run the development server with monitoring:</p>
                            <code class="mt-1 block text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded font-mono">composer run dev</code>
                            <p class="text-xs text-gray-500 mt-1">This starts all services including the log monitor</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium">2</div>
                        <div>
                            <p class="text-sm font-medium">Or run monitoring separately:</p>
                            <code class="mt-1 block text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded font-mono">php artisan mcp:watch-logs --auto-fix</code>
                            <p class="text-xs text-gray-500 mt-1">Runs only the log monitoring service</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium">3</div>
                        <div>
                            <p class="text-sm font-medium">Monitor the console output</p>
                            <p class="text-xs text-gray-500 mt-1">Watch for detected errors and automatic Claude notifications</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-medium">✓</div>
                        <div>
                            <p class="text-sm font-medium">Errors are automatically sent to Claude for analysis and fixes!</p>
                            <p class="text-xs text-gray-500 mt-1">No more manual copy/paste - Claude gets full context immediately</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::card>

        {{-- System Information --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-filament::card>
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        System Information
                    </h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Monitoring Command:</dt>
                            <dd class="text-sm font-mono">{{ $monitoringStats['log_monitoring_command'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Integration Status:</dt>
                            <dd class="text-sm">{{ $monitoringStats['integration_status'] ?? 'Unknown' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Last Updated:</dt>
                            <dd class="text-sm">{{ $monitoringStats['last_updated'] ?? 'Never' }}</dd>
                        </div>
                    </dl>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Benefits
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center space-x-2">
                            <x-filament::icon icon="heroicon-o-bolt" class="w-4 h-4 text-yellow-500" />
                            <span>Instant error detection and notification</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <x-filament::icon icon="heroicon-o-document-text" class="w-4 h-4 text-blue-500" />
                            <span>Full file context and stack traces</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <x-filament::icon icon="heroicon-o-cpu-chip" class="w-4 h-4 text-green-500" />
                            <span>Automated fix suggestions</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <x-filament::icon icon="heroicon-o-clock" class="w-4 h-4 text-purple-500" />
                            <span>Eliminates manual error reporting</span>
                        </li>
                    </ul>
                </div>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>