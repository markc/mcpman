<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Log Statistics -->
        @php $stats = $this->getLogStats(); @endphp
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Log Statistics
                </h3>
            </div>
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Entries</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_entries']) }}</p>
                    </div>
                    @foreach($stats['by_level'] as $level => $count)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ ucfirst($level) }}</p>
                            <p class="text-xl font-bold text-{{ $this->getLevelColor($level) }}-600 dark:text-{{ $this->getLevelColor($level) }}-400">
                                {{ number_format($count) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Log Entries -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Log Entries
                </h3>
                <div class="ml-auto">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ count($this->logData) }} entries
                    </span>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                @if(count($this->logData) > 0)
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($this->logData as $entry)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-start gap-x-3">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset
                                            @if($this->getLevelColor($entry['level']) === 'danger') bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20
                                            @elseif($this->getLevelColor($entry['level']) === 'warning') bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/20
                                            @elseif($this->getLevelColor($entry['level']) === 'info') bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/20
                                            @elseif($this->getLevelColor($entry['level']) === 'primary') bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-400/10 dark:text-indigo-400 dark:ring-indigo-400/20
                                            @else bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20
                                            @endif">
                                            {{ strtoupper($entry['level']) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-x-2">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $entry['timestamp'] }}
                                            </p>
                                        </div>
                                        <div class="mt-2">
                                            <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 font-mono">{{ trim($entry['message']) }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-filament::icon 
                            icon="heroicon-o-document-text" 
                            class="mx-auto h-12 w-12 text-gray-400"
                        />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No log entries found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Try adjusting your filters or check if the log file exists.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Debug Tools -->
        <div class="fi-section rounded-xl bg-green-50 shadow-sm ring-1 ring-green-200 dark:bg-green-900/20 dark:ring-green-800">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-green-900 dark:text-green-100">
                    Debug Information
                </h3>
            </div>
            <div class="fi-section-content px-6 py-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">PHP Version</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ PHP_VERSION }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Laravel Version</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ app()->version() }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Environment</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ app()->environment() }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Debug Mode</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>