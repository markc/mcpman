<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Widgets are automatically rendered by Filament via getHeaderWidgets() --}}

        {{-- Additional content will be displayed below the widgets --}}

        <div class="grid grid-cols-1 gap-6">

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