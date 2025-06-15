<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Export Section -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Export Data
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Export your MCP data for backup or migration
                    </p>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                {{ $this->exportForm }}
                
                <div class="mt-6">
                    <x-filament::button 
                        wire:click="exportData"
                        icon="heroicon-o-arrow-down-tray"
                        size="lg"
                    >
                        Export Data
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Import Data
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Import previously exported MCP data
                    </p>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                {{ $this->importForm }}
                
                <div class="mt-6">
                    <x-filament::button 
                        wire:click="importData"
                        icon="heroicon-o-arrow-up-tray"
                        size="lg"
                        color="success"
                    >
                        Import Data
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- Recent Exports -->
        @if(count($this->recentExports) > 0)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <div class="grid flex-1 gap-y-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Recent Exports
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            Download or manage your recent export files
                        </p>
                    </div>
                </div>
                <div class="fi-section-content px-6 py-4">
                    <div class="space-y-3">
                        @foreach($this->recentExports as $export)
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-x-3">
                                        <x-filament::icon 
                                            icon="heroicon-o-document-arrow-down" 
                                            class="h-6 w-6 text-gray-400"
                                        />
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $export['name'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $export['created']->format('M j, Y g:i A') }} • 
                                                {{ number_format($export['size'] / 1024, 1) }} KB
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-x-2">
                                    <x-filament::button
                                        tag="a"
                                        :href="$export['url']"
                                        target="_blank"
                                        icon="heroicon-o-arrow-down-tray"
                                        size="sm"
                                        color="primary"
                                    >
                                        Download
                                    </x-filament::button>
                                    <x-filament::button
                                        wire:click="deleteExport('{{ $export['name'] }}')"
                                        icon="heroicon-o-trash"
                                        size="sm"
                                        color="danger"
                                        wire:confirm="Are you sure you want to delete this export file?"
                                    >
                                        Delete
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Help Section -->
        <div class="fi-section rounded-xl bg-blue-50 shadow-sm ring-1 ring-blue-200 dark:bg-blue-900/20 dark:ring-blue-800">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-blue-900 dark:text-blue-100">
                        Import/Export Help
                    </h3>
                </div>
            </div>
            <div class="fi-section-content px-6 py-4">
                <div class="prose prose-sm max-w-none text-blue-800 dark:prose-invert dark:text-blue-200">
                    <h4>Export Formats:</h4>
                    <ul class="text-sm">
                        <li><strong>JSON:</strong> Single file containing all data, ideal for programmatic use</li>
                        <li><strong>ZIP:</strong> Multiple organized files in a compressed archive</li>
                        <li><strong>CSV:</strong> Spreadsheet-compatible format for data analysis</li>
                    </ul>
                    
                    <h4>Security Notes:</h4>
                    <ul class="text-sm">
                        <li>API keys and authentication data are excluded by default for security</li>
                        <li>Exported files are automatically deleted after 30 days</li>
                        <li>Only export what you need to minimize file size and exposure</li>
                    </ul>
                    
                    <h4>Import Guidelines:</h4>
                    <ul class="text-sm">
                        <li>Only import files exported from MCPman to ensure compatibility</li>
                        <li>Use "Skip Duplicates" to avoid creating duplicate records</li>
                        <li>Templates are made private by default when importing</li>
                        <li>Connection authentication data is cleared for security</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>