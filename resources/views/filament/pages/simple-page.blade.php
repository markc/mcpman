<x-filament-panels::page>
    @if($this->hasWidgets())
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :columns="$this->getWidgetsColumns()"
        />
    @endif
</x-filament-panels::page>