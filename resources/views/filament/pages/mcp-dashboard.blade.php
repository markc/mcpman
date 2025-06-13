<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Connection Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Total Connections
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->connectionStats['total'] ?? 0 }}
                                </div>
                                <div class="ml-2 flex items-baseline text-sm">
                                    <span class="text-green-600">{{ $this->connectionStats['active'] ?? 0 }} active</span>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Dataset Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Datasets
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->systemStats['datasets'] ?? 0 }}
                                </div>
                                @if(($this->systemStats['recent_datasets'] ?? 0) > 0)
                                    <div class="ml-2 flex items-baseline text-sm">
                                        <span class="text-blue-600">+{{ $this->systemStats['recent_datasets'] }} this week</span>
                                    </div>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- Document Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Documents
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->systemStats['documents'] ?? 0 }}
                                </div>
                                @if(($this->systemStats['recent_documents'] ?? 0) > 0)
                                    <div class="ml-2 flex items-baseline text-sm">
                                        <span class="text-blue-600">+{{ $this->systemStats['recent_documents'] }} this week</span>
                                    </div>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <!-- API Key Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Active API Keys
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ $this->systemStats['api_keys'] ?? 0 }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Connection Status -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                    MCP Connection Status
                </h3>
                
                @if($this->connections->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->connections as $connection)
                            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        @if($connection->status === 'active')
                                            <div class="h-3 w-3 bg-green-500 rounded-full"></div>
                                        @elseif($connection->status === 'error')
                                            <div class="h-3 w-3 bg-red-500 rounded-full"></div>
                                        @else
                                            <div class="h-3 w-3 bg-gray-400 rounded-full"></div>
                                        @endif
                                    </div>
                                    
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $connection->name }}
                                        </h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $connection->transport_type }} • {{ $connection->endpoint_url }}
                                        </p>
                                        @if($connection->last_connected_at)
                                            <p class="text-xs text-gray-400">
                                                Last connected: {{ $connection->last_connected_at->diffForHumans() }}
                                            </p>
                                        @endif
                                        @if($connection->last_error)
                                            <p class="text-xs text-red-500 mt-1">
                                                Error: {{ Str::limit($connection->last_error, 100) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($connection->status === 'active')
                                            bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($connection->status === 'error')
                                            bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @else
                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                        @endif
                                    ">
                                        {{ ucfirst($connection->status) }}
                                    </span>
                                    
                                    <button 
                                        wire:click="testConnection({{ $connection->id }})"
                                        class="inline-flex items-center px-3 py-1 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    >
                                        Test
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No MCP connections</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Get started by creating your first MCP connection.
                        </p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Recent Activity -->
        @if(!empty($this->recentActivity))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Recent Activity
                    </h3>
                    
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($this->recentActivity as $index => $activity)
                                <li class="{{ $index < count($this->recentActivity) - 1 ? 'pb-8' : '' }}">
                                    <div class="relative">
                                        @if($index < count($this->recentActivity) - 1)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-600" aria-hidden="true"></span>
                                        @endif
                                        
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-800
                                                    @if($activity['type'] === 'connection')
                                                        @if($activity['status'] === 'active')
                                                            bg-green-500
                                                        @else
                                                            bg-red-500
                                                        @endif
                                                    @elseif($activity['type'] === 'dataset')
                                                        bg-blue-500
                                                    @else
                                                        bg-purple-500
                                                    @endif
                                                ">
                                                    @if($activity['type'] === 'connection')
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                        </svg>
                                                    @elseif($activity['type'] === 'dataset')
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                    @else
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    @endif
                                                </span>
                                            </div>
                                            
                                            <div class="min-w-0 flex-1">
                                                <div>
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $activity['message'] }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                                        {{ $activity['timestamp']->diffForHumans() }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>