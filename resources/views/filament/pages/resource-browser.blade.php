<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search and Filters -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Resource Discovery & Management
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Statistics Dashboard -->
        @if(!empty($this->resourceStatistics))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Resource Statistics
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="text-center p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ $this->resourceStatistics['total_resources'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Resources</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-success-50 dark:bg-success-900/20">
                            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                                {{ $this->resourceStatistics['cached_resources'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Cached Resources</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                                {{ $this->resourceStatistics['public_resources'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Public Resources</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                            <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                                {{ $this->resourceStatistics['connections_with_resources'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Connections</div>
                        </div>
                    </div>

                    <!-- Resource Types -->
                    @if(!empty($this->resourceStatistics['types']))
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Resources by Type</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach($this->resourceStatistics['types'] as $type => $count)
                                    <div class="text-center p-3 rounded border border-gray-200 dark:border-gray-700">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $count }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 capitalize">{{ str_replace('_', ' ', $type) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Resource Browser -->
            <div class="lg:col-span-2">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                Resource Browser
                            </h3>
                            @if(!empty($this->discoveredResources))
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    ({{ count($this->discoveredResources) }} resources)
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="fi-section-content px-6 py-4">
                        @if(empty($this->discoveredResources))
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                <x-filament::icon
                                    icon="heroicon-o-folder"
                                    class="mx-auto h-12 w-12 text-gray-400 mb-4"
                                />
                                <p class="text-lg font-medium mb-2">No resources discovered yet</p>
                                <p class="text-sm">Select an MCP connection above or click "Discover All Resources" to find available resources.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($this->discoveredResources as $resource)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors"
                                         wire:click="selectResource({{ $resource['id'] }})">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <x-filament::icon
                                                    icon="heroicon-o-document"
                                                    class="w-6 h-6 text-gray-500"
                                                />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                                        {{ $resource['name'] }}
                                                    </h4>
                                                    <span class="text-xs px-2 py-1 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                                                        {{ ucfirst(str_replace('_', ' ', $resource['type'])) }}
                                                    </span>
                                                </div>
                                                
                                                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                                    {{ $resource['path_or_uri'] }}
                                                </p>
                                                
                                                @if(!empty($resource['description']))
                                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ Str::limit($resource['description'], 100) }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Resource Details -->
            <div class="lg:col-span-1">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                Resource Details
                            </h3>
                        </div>
                    </div>
                    
                    <div class="fi-section-content px-6 py-4">
                        @if($this->selectedResource)
                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                                        {{ $this->selectedResource->name }}
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $this->selectedResource->path_or_uri }}
                                    </p>
                                </div>

                                @if($this->selectedResource->description)
                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $this->selectedResource->description }}
                                        </p>
                                    </div>
                                @endif

                                @if($this->resourceContent)
                                    <div>
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Content Preview</h5>
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded p-3 max-h-64 overflow-y-auto">
                                            @if(is_string($this->resourceContent['content']))
                                                <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ Str::limit($this->resourceContent['content'], 500) }}</pre>
                                            @else
                                                <pre class="text-sm text-gray-700 dark:text-gray-300">{{ json_encode($this->resourceContent['content'], JSON_PRETTY_PRINT) }}</pre>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                <x-filament::icon
                                    icon="heroicon-o-document-magnifying-glass"
                                    class="mx-auto h-8 w-8 text-gray-400 mb-2"
                                />
                                <p class="text-sm">Select a resource to view details</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>