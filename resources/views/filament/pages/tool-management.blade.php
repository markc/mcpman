<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search and Filters -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Tool Discovery & Management
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Statistics Dashboard -->
        @if(!empty($this->toolStatistics))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Tool Statistics
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="text-center p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ $this->toolStatistics['total_tools'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Tools</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-success-50 dark:bg-success-900/20">
                            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                                {{ $this->toolStatistics['active_tools'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Active Tools</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                                {{ $this->toolStatistics['favorite_tools'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Favorites</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                            <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                                {{ $this->toolStatistics['connections_with_tools'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Connections</div>
                        </div>
                    </div>

                    <!-- Category Breakdown -->
                    @if(!empty($this->toolStatistics['categories']))
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Tools by Category</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach($this->toolStatistics['categories'] as $category => $count)
                                    <div class="text-center p-3 rounded border border-gray-200 dark:border-gray-700">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $count }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 capitalize">{{ $category }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Most Used Tools -->
                    @if(!empty($this->toolStatistics['most_used']))
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Most Used Tools</h4>
                            <div class="space-y-2">
                                @foreach(array_slice($this->toolStatistics['most_used'], 0, 5) as $tool)
                                    <div class="flex justify-between items-center py-2 px-3 rounded bg-gray-50 dark:bg-gray-800">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $tool['name'] }}</span>
                                            <span class="text-xs px-2 py-1 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                                                {{ $tool['category'] }}
                                            </span>
                                        </div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $tool['usage_count'] }} uses</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Discovered Tools -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Discovered Tools
                    </h3>
                    @if(!empty($this->discoveredTools))
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            ({{ count($this->discoveredTools) }} tools)
                        </span>
                    @endif
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                @if(empty($this->discoveredTools))
                    <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                        <x-filament::icon
                            icon="heroicon-o-wrench"
                            class="mx-auto h-12 w-12 text-gray-400 mb-4"
                        />
                        <p class="text-lg font-medium mb-2">No tools discovered yet</p>
                        <p class="text-sm">Select an MCP connection above or click "Discover All Tools" to find available tools.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->discoveredTools as $tool)
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-start mb-3">
                                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $tool['name'] }}
                                    </h4>
                                    <div class="flex gap-1">
                                        <button 
                                            wire:click="toggleToolFavorite({{ $tool['id'] }})"
                                            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                                            title="Toggle Favorite"
                                        >
                                            <x-filament::icon
                                                icon="{{ $tool['is_favorite'] ? 'heroicon-s-star' : 'heroicon-o-star' }}"
                                                class="w-4 h-4 {{ $tool['is_favorite'] ? 'text-yellow-500' : 'text-gray-400' }}"
                                            />
                                        </button>
                                        <button 
                                            wire:click="toggleToolActive({{ $tool['id'] }})"
                                            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                                            title="Toggle Active"
                                        >
                                            <x-filament::icon
                                                icon="{{ $tool['is_active'] ? 'heroicon-s-check-circle' : 'heroicon-o-x-circle' }}"
                                                class="w-4 h-4 {{ $tool['is_active'] ? 'text-green-500' : 'text-red-500' }}"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    @if(!empty($tool['description']))
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ Str::limit($tool['description'], 100) }}
                                        </p>
                                    @endif

                                    <div class="flex items-center gap-2">
                                        <span class="text-xs px-2 py-1 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                                            {{ $tool['category'] }}
                                        </span>
                                        @if(!empty($tool['version']))
                                            <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                v{{ $tool['version'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if(!empty($tool['tags']))
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @foreach(array_slice($tool['tags'], 0, 3) as $tag)
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300">
                                                    {{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400 mt-3">
                                        <span>{{ $tool['usage_count'] ?? 0 }} uses</span>
                                        @if(!empty($tool['success_rate']))
                                            <span>{{ number_format($tool['success_rate'], 1) }}% success</span>
                                        @endif
                                        @if(!empty($tool['average_execution_time']))
                                            <span>{{ number_format($tool['average_execution_time'], 2) }}s avg</span>
                                        @endif
                                    </div>

                                    @if(!empty($tool['parameters']))
                                        <details class="mt-3">
                                            <summary class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer hover:text-gray-800 dark:hover:text-gray-200">
                                                Parameters ({{ count($tool['parameters']) }})
                                            </summary>
                                            <div class="mt-2 space-y-1">
                                                @foreach($tool['parameters'] as $param => $schema)
                                                    <div class="text-xs">
                                                        <span class="font-mono text-primary-600 dark:text-primary-400">{{ $param }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400">({{ $schema['type'] ?? 'unknown' }})</span>
                                                        @if(in_array($param, $tool['required_parameters'] ?? []))
                                                            <span class="text-red-500">*</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Tool Compositions -->
        @if(!empty($this->toolCompositions))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Tool Compositions
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($this->toolCompositions as $composition)
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                    {{ $composition['name'] }}
                                </h4>
                                @if(!empty($composition['description']))
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        {{ $composition['description'] }}
                                    </p>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ count($composition['tools']) }} tools in composition
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>