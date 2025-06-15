<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Health Overview Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($this->getHealthCards() as $card)
                <x-filament::card>
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <x-filament::icon 
                                :icon="$card['icon']" 
                                class="w-8 h-8 text-{{ $card['color'] }}-500"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $card['label'] }}
                            </p>
                            <p class="text-2xl font-bold text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-400">
                                {{ $card['value'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $card['description'] }}
                            </p>
                        </div>
                    </div>
                </x-filament::card>
            @endforeach
        </div>

        {{-- System Components Status --}}
        <x-filament::card>
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    System Components
                </h3>
                <div class="space-y-3">
                    @foreach($healthData['components'] ?? [] as $component => $status)
                        <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $component }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $this->getComponentStatusColor($status) }}-100 text-{{ $this->getComponentStatusColor($status) }}-800 dark:bg-{{ $this->getComponentStatusColor($status) }}-900 dark:text-{{ $this->getComponentStatusColor($status) }}-200">
                                {{ $status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-filament::card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Performance Metrics --}}
            <x-filament::card>
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Performance Metrics
                    </h3>
                    <div class="space-y-3">
                        @foreach($healthData['performance'] ?? [] as $metric => $value)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $metric }}
                                </span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $value }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::card>

            {{-- Recommendations --}}
            <x-filament::card>
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Recommendations
                    </h3>
                    <div class="space-y-3">
                        @forelse($healthData['recommendations'] ?? [] as $recommendation)
                            <div class="flex items-start space-x-3 p-3 rounded-lg bg-{{ $this->getRecommendationColor($recommendation['type']) }}-50 dark:bg-{{ $this->getRecommendationColor($recommendation['type']) }}-900/20">
                                <x-filament::icon 
                                    :icon="$this->getRecommendationIcon($recommendation['type'])" 
                                    class="w-5 h-5 text-{{ $this->getRecommendationColor($recommendation['type']) }}-500 mt-0.5 flex-shrink-0"
                                />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-{{ $this->getRecommendationColor($recommendation['type']) }}-800 dark:text-{{ $this->getRecommendationColor($recommendation['type']) }}-200">
                                        {{ $recommendation['message'] }}
                                    </p>
                                    <p class="text-xs text-{{ $this->getRecommendationColor($recommendation['type']) }}-600 dark:text-{{ $this->getRecommendationColor($recommendation['type']) }}-300 mt-1">
                                        {{ $recommendation['action'] }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                                No recommendations available
                            </p>
                        @endforelse
                    </div>
                </div>
            </x-filament::card>
        </div>

        {{-- System Errors --}}
        @if(!empty($healthData['errors']))
            <x-filament::card>
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        System Errors
                    </h3>
                    <div class="space-y-4">
                        @foreach($healthData['errors'] as $error)
                            <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                <div class="flex items-start space-x-3">
                                    <x-filament::icon 
                                        icon="heroicon-o-exclamation-triangle" 
                                        class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
                                    />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                            {{ $error['message'] }}
                                        </p>
                                        @if(!empty($error['context']))
                                            <details class="mt-2">
                                                <summary class="text-xs text-red-600 dark:text-red-300 cursor-pointer hover:text-red-800 dark:hover:text-red-100">
                                                    Show Details
                                                </summary>
                                                <pre class="mt-2 text-xs text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/40 p-2 rounded overflow-x-auto">{{ json_encode($error['context'], JSON_PRETTY_PRINT) }}</pre>
                                            </details>
                                        @endif
                                        <p class="text-xs text-red-600 dark:text-red-300 mt-1">
                                            {{ \Carbon\Carbon::parse($error['timestamp'])->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::card>
        @endif

        {{-- Auto-refresh Notice --}}
        <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Health status refreshes automatically every 60 seconds
            </p>
        </div>
    </div>
</x-filament-panels::page>