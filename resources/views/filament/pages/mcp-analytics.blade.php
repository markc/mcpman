<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Section -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Time Period & Filters
                    </h3>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Real-time Metrics -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Real-time Metrics
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Live data updated every 30 seconds
                    </p>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach($this->getRealTimeCards() as $card)
                        <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-shrink-0">
                                    <x-filament::icon 
                                        :icon="$card['icon']" 
                                        class="h-8 w-8 text-{{ $card['color'] }}-500"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ $card['label'] }}
                                    </p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ $card['value'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $card['description'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Period Summary
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Analytics for the selected time period
                    </p>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($this->getSummaryCards() as $card)
                        <div class="rounded-xl bg-gray-50 p-6 dark:bg-gray-800">
                            <div class="flex items-center gap-x-3">
                                <div class="flex-shrink-0">
                                    <x-filament::icon 
                                        :icon="$card['icon']" 
                                        class="h-10 w-10 text-{{ $card['color'] }}-500"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ $card['label'] }}
                                    </p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                        {{ $card['value'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $card['description'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Top Connections and Tools -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Top Connections -->
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <div class="grid flex-1 gap-y-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Top Connections
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Most active MCP connections
                        </p>
                    </div>
                </div>
                <div class="fi-section-content px-6 py-4">
                    @if(isset($this->dashboardData['top_connections']) && $this->dashboardData['top_connections']->count() > 0)
                        <div class="space-y-3">
                            @foreach($this->dashboardData['top_connections'] as $connection)
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $connection->connection->name ?? 'Unknown Connection' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($connection->event_count) }} events • 
                                            {{ number_format($connection->success_rate, 1) }}% success rate
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ number_format($connection->avg_duration ?? 0) }}ms
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">avg time</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No connection data available.</p>
                    @endif
                </div>
            </div>

            <!-- Top Tools -->
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <div class="grid flex-1 gap-y-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Top Tools
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Most frequently used tools
                        </p>
                    </div>
                </div>
                <div class="fi-section-content px-6 py-4">
                    @if(isset($this->dashboardData['top_tools']) && $this->dashboardData['top_tools']->count() > 0)
                        <div class="space-y-3">
                            @foreach($this->dashboardData['top_tools'] as $tool)
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $tool->tool->name ?? 'Unknown Tool' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ ucfirst($tool->tool->category ?? 'general') }} • 
                                            {{ number_format($tool->usage_count) }} uses
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ number_format($tool->success_rate, 1) }}%
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">success</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No tool usage data available.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Error Analysis -->
        @if(isset($this->dashboardData['error_analysis']['common_errors']) && $this->dashboardData['error_analysis']['common_errors']->count() > 0)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <div class="grid flex-1 gap-y-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Common Errors
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Most frequent error types and messages
                        </p>
                    </div>
                </div>
                <div class="fi-section-content px-6 py-4">
                    <div class="space-y-3">
                        @foreach($this->dashboardData['error_analysis']['common_errors'] as $error)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                <div class="flex items-start gap-x-3">
                                    <x-filament::icon 
                                        icon="heroicon-o-exclamation-triangle" 
                                        class="h-5 w-5 text-red-500 mt-0.5"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                            {{ ucfirst(str_replace('_', ' ', $error->event_type)) }}
                                        </p>
                                        @if($error->error_message)
                                            <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                                {{ Str::limit($error->error_message, 100) }}
                                            </p>
                                        @endif
                                        <p class="text-xs text-red-600 dark:text-red-400 mt-2">
                                            {{ number_format($error->error_count) }} occurrences
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>