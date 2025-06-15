<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recommendations
        </x-slot>

        <div class="space-y-3">
            @forelse($this->getViewData()['recommendations'] as $recommendation)
                <div class="p-3 rounded-lg bg-{{ $this->getRecommendationColor($recommendation['type']) }}-50 dark:bg-{{ $this->getRecommendationColor($recommendation['type']) }}-900/20">
                    <p class="text-sm font-medium text-{{ $this->getRecommendationColor($recommendation['type']) }}-800 dark:text-{{ $this->getRecommendationColor($recommendation['type']) }}-200">
                        {{ $recommendation['message'] }}
                    </p>
                    <p class="text-xs text-{{ $this->getRecommendationColor($recommendation['type']) }}-600 dark:text-{{ $this->getRecommendationColor($recommendation['type']) }}-300 mt-1">
                        {{ $recommendation['action'] }}
                    </p>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-filament::icon 
                        icon="heroicon-o-check-circle" 
                        class="w-12 h-12 text-success-500 mx-auto mb-3"
                    />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No recommendations - all systems optimal
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>