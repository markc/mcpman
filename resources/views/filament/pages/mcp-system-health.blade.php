<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Widgets are automatically rendered by Filament via getHeaderWidgets() --}}
        {{-- Row 1: Health Overview (4 stats) --}}
        {{-- Row 2: System Components (4 stats) --}}
        {{-- Row 3: Recommendations & System Errors (50% each) --}}

        {{-- Auto-refresh Notice --}}
        <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Health status refreshes automatically every 60 seconds
            </p>
        </div>
    </div>
</x-filament-panels::page>