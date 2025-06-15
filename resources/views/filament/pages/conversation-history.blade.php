<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search and Filters -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Search & Filter Conversations
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>

        <!-- Statistics Dashboard -->
        @if(!empty($this->conversationStats))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Conversation Statistics
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="text-center p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ $this->conversationStats['total_conversations'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Conversations</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-success-50 dark:bg-success-900/20">
                            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                                {{ $this->conversationStats['active_conversations'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Active Conversations</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-info-50 dark:bg-info-900/20">
                            <div class="text-2xl font-bold text-info-600 dark:text-info-400">
                                {{ $this->conversationStats['total_messages'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Messages</div>
                        </div>
                        
                        <div class="text-center p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                                {{ round(($this->conversationStats['total_duration_minutes'] ?? 0) / 60, 1) }}h
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Duration</div>
                        </div>
                    </div>

                    <!-- Most Used Connections -->
                    @if(!empty($this->conversationStats['most_used_connections']))
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Most Used Connections</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                @foreach(array_slice($this->conversationStats['most_used_connections'], 0, 3) as $connection)
                                    <div class="text-center p-3 rounded border border-gray-200 dark:border-gray-700">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $connection['count'] }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ $connection['name'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Tool Usage Summary -->
                    @if(!empty($this->conversationStats['tool_usage_summary']))
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Top Tools Used</h4>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                @foreach(array_slice($this->conversationStats['tool_usage_summary'], 0, 5) as $toolName => $usage)
                                    <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $usage['calls'] }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 truncate">{{ $toolName }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Conversation List -->
            <div class="lg:col-span-2">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                Conversation History
                            </h3>
                            @if(!empty($this->conversations))
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    ({{ count($this->conversations) }} conversations)
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="fi-section-content px-6 py-4">
                        @if(empty($this->conversations))
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                <x-filament::icon
                                    icon="heroicon-o-chat-bubble-left-right"
                                    class="mx-auto h-12 w-12 text-gray-400 mb-4"
                                />
                                <p class="text-lg font-medium mb-2">No conversations found</p>
                                <p class="text-sm">Start a new conversation or adjust your filters to see results.</p>
                            </div>
                        @else
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @foreach($this->conversations as $conversation)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors {{ $this->selectedConversation?->id == $conversation['id'] ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900/20' : '' }}"
                                         wire:click="selectConversation({{ $conversation['id'] }})">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <x-filament::icon
                                                    icon="heroicon-o-chat-bubble-left-right"
                                                    class="w-6 h-6 text-gray-500"
                                                />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                                        {{ $conversation['title'] }}
                                                    </h4>
                                                    <span class="text-xs px-2 py-1 rounded bg-{{ $conversation['status'] === 'active' ? 'success' : ($conversation['status'] === 'archived' ? 'warning' : 'gray') }}-100 dark:bg-{{ $conversation['status'] === 'active' ? 'success' : ($conversation['status'] === 'archived' ? 'warning' : 'gray') }}-900 text-{{ $conversation['status'] === 'active' ? 'success' : ($conversation['status'] === 'archived' ? 'warning' : 'gray') }}-700 dark:text-{{ $conversation['status'] === 'active' ? 'success' : ($conversation['status'] === 'archived' ? 'warning' : 'gray') }}-300">
                                                        {{ ucfirst($conversation['status']) }}
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400 mb-1">
                                                    <span>{{ $conversation['connection_name'] }} ({{ $conversation['connection_type'] }})</span>
                                                    <span>{{ $conversation['message_count'] }} messages</span>
                                                    <span>{{ $conversation['duration_minutes'] }}min</span>
                                                </div>
                                                
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Started {{ $conversation['started_at'] }} • Last active {{ $conversation['last_activity_at'] }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Conversation Details -->
            <div class="lg:col-span-1">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                Conversation Details
                            </h3>
                        </div>
                    </div>
                    
                    <div class="fi-section-content px-6 py-4">
                        @if($this->selectedConversation)
                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                                        {{ $this->selectedConversation->title }}
                                    </h4>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                        <div><strong>Connection:</strong> {{ $this->selectedConversation->mcpConnection->name }}</div>
                                        <div><strong>Status:</strong> {{ ucfirst($this->selectedConversation->status) }}</div>
                                        <div><strong>Session ID:</strong> {{ $this->selectedConversation->session_id }}</div>
                                        <div><strong>Messages:</strong> {{ $this->selectedConversation->message_count }}</div>
                                        <div><strong>Duration:</strong> {{ $this->selectedConversation->getDurationInMinutes() }} minutes</div>
                                        <div><strong>Started:</strong> {{ $this->selectedConversation->started_at?->format('M j, Y g:i A') }}</div>
                                    </div>
                                </div>

                                @if(!empty($this->selectedConversationMessages))
                                    <div>
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Message Preview</h5>
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded p-3 max-h-64 overflow-y-auto space-y-2">
                                            @foreach(array_slice($this->selectedConversationMessages, 0, 5) as $message)
                                                <div class="text-sm">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="text-xs px-2 py-1 rounded bg-{{ $message['role'] === 'user' ? 'blue' : ($message['role'] === 'assistant' ? 'green' : 'gray') }}-100 dark:bg-{{ $message['role'] === 'user' ? 'blue' : ($message['role'] === 'assistant' ? 'green' : 'gray') }}-900 text-{{ $message['role'] === 'user' ? 'blue' : ($message['role'] === 'assistant' ? 'green' : 'gray') }}-700 dark:text-{{ $message['role'] === 'user' ? 'blue' : ($message['role'] === 'assistant' ? 'green' : 'gray') }}-300">
                                                            {{ ucfirst($message['role']) }}
                                                        </span>
                                                        <span class="text-xs text-gray-500">{{ $message['sent_at'] }}</span>
                                                    </div>
                                                    <div class="text-gray-700 dark:text-gray-300 pl-2 border-l-2 border-gray-200 dark:border-gray-600">
                                                        {{ Str::limit(strip_tags($message['content']), 100) }}
                                                    </div>
                                                </div>
                                            @endforeach
                                            
                                            @if(count($this->selectedConversationMessages) > 5)
                                                <div class="text-xs text-gray-500 text-center pt-2">
                                                    ... and {{ count($this->selectedConversationMessages) - 5 }} more messages
                                                </div>
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
                                <p class="text-sm">Select a conversation to view details</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>