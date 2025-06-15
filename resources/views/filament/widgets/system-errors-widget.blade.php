<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            System Errors
        </x-slot>

        <div class="space-y-4">
            @forelse($this->getViewData()['errors'] as $error)
                <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                    <p class="text-sm font-medium text-danger-800 dark:text-danger-200">
                        {{ $error['message'] }}
                    </p>
                    @if(!empty($error['context']))
                        <details class="mt-2">
                            <summary class="text-xs text-danger-600 dark:text-danger-300 cursor-pointer hover:text-danger-800 dark:hover:text-danger-100">
                                Show Details
                            </summary>
                            <pre class="mt-2 text-xs text-danger-700 dark:text-danger-300 bg-danger-100 dark:bg-danger-900/40 p-2 rounded overflow-x-auto">{{ json_encode($error['context'], JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    @endif
                    <p class="text-xs text-danger-600 dark:text-danger-300 mt-1">
                        {{ \Carbon\Carbon::parse($error['timestamp'])->diffForHumans() }}
                    </p>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-filament::icon 
                        icon="heroicon-o-shield-check" 
                        class="w-12 h-12 text-success-500 mx-auto mb-3"
                    />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No system errors detected
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>