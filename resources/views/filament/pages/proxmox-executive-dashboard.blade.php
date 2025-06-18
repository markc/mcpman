<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        PHP/Laravel Development Platform Analytics
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Executive dashboard for LEMP stack development environments - Nginx, PHP 8.4, Laravel 12, Filament v4, MariaDB
                    </p>
                </div>
                <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <x-heroicon-o-server class="w-4 h-4 mr-1" />
                        {{ $totalClusters }} Clusters
                    </div>
                    <div class="flex items-center">
                        <x-heroicon-o-cube class="w-4 h-4 mr-1" />
                        {{ $activeEnvironments }}/{{ $totalEnvironments }} Active
                    </div>
                    <div class="flex items-center">
                        <x-heroicon-o-currency-dollar class="w-4 h-4 mr-1" />
                        ${{ number_format($totalMonthlyCost, 2) }}/month
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <x-heroicon-o-cpu-chip class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average CPU Usage</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $averageUtilization['cpu'] }}%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <x-heroicon-o-circle-stack class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Memory Usage</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $averageUtilization['memory'] }}%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <x-heroicon-o-archive-box class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Storage Usage</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $averageUtilization['storage'] }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Widgets Grid -->
        <div class="space-y-6">
            @foreach ($this->getWidgets() as $widget)
                @livewire($widget)
            @endforeach
        </div>

        <!-- Key Insights Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <x-heroicon-o-light-bulb class="w-5 h-5 inline mr-2" />
                Key Insights & Recommendations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-900 dark:text-white">Cost Optimization</h4>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <x-heroicon-o-arrow-trending-down class="w-4 h-4 mt-0.5 mr-2 text-green-500" />
                            Consider implementing auto-scaling for development environments
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-clock class="w-4 h-4 mt-0.5 mr-2 text-blue-500" />
                            Review environments with high idle time for potential consolidation
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-archive-box-x-mark class="w-4 h-4 mt-0.5 mr-2 text-orange-500" />
                            Implement automated cleanup policies for expired environments
                        </li>
                    </ul>
                </div>
                <div class="space-y-3">
                    <h4 class="font-medium text-gray-900 dark:text-white">Performance Optimization</h4>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <x-heroicon-o-chart-bar-square class="w-4 h-4 mt-0.5 mr-2 text-purple-500" />
                            Monitor clusters with high resource utilization
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-server-stack class="w-4 h-4 mt-0.5 mr-2 text-indigo-500" />
                            Consider load balancing across available nodes
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-shield-check class="w-4 h-4 mt-0.5 mr-2 text-green-500" />
                            Ensure backup policies are properly configured
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Action Items -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-4">
                <x-heroicon-o-clipboard-document-check class="w-5 h-5 inline mr-2" />
                Recommended Actions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('filament.admin.resources.proxmox-clusters.index') }}" 
                   class="block p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <x-heroicon-o-server-stack class="w-8 h-8 text-blue-500 mr-3" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Manage Clusters</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Configure and monitor Proxmox clusters</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('filament.admin.resources.development-environments.index') }}" 
                   class="block p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <x-heroicon-o-cube class="w-8 h-8 text-green-500 mr-3" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Development Environments</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Create and manage dev environments</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('filament.admin.resources.development-environments.create') }}" 
                   class="block p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <x-heroicon-o-plus-circle class="w-8 h-8 text-purple-500 mr-3" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Create Environment</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Deploy a new development environment</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>