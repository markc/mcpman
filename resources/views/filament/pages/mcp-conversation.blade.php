<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Conversation Display -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Conversation
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div id="conversation-container" class="max-h-96 overflow-y-auto space-y-4">
                    @if(empty($this->conversation))
                        <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                            <x-filament::icon
                                icon="heroicon-o-chat-bubble-left-ellipsis"
                                class="mx-auto h-12 w-12 text-gray-400"
                            />
                            <p class="mt-2">No messages yet. Start a conversation with Claude Code!</p>
                        </div>
                    @else
                        @foreach($this->conversation as $message)
                            <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                                    @if($message['role'] === 'user')
                                        bg-primary-500 text-white
                                    @elseif($message['role'] === 'assistant')
                                        bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100
                                    @elseif($message['role'] === 'tool_call')
                                        bg-warning-100 dark:bg-warning-900 text-warning-900 dark:text-warning-100 border border-warning-300
                                    @elseif($message['role'] === 'tool_result')
                                        bg-success-100 dark:bg-success-900 text-success-900 dark:text-success-100 border border-success-300
                                    @else
                                        bg-danger-100 dark:bg-danger-900 text-danger-900 dark:text-danger-100 border border-danger-300
                                    @endif
                                ">
                                    <div class="text-sm">
                                        @if($message['role'] === 'tool_call')
                                            <div class="font-medium">🔧 Tool Call: {{ $message['tool'] ?? 'Unknown' }}</div>
                                            @if(!empty($message['arguments']))
                                                <div class="mt-1 text-xs opacity-75">
                                                    {{ json_encode($message['arguments']) }}
                                                </div>
                                            @endif
                                        @elseif($message['role'] === 'tool_result')
                                            <div class="font-medium">✅ Tool Result</div>
                                        @elseif($message['role'] === 'error')
                                            <div class="font-medium">❌ Error</div>
                                        @endif
                                        
                                        <div class="{{ $message['role'] === 'tool_call' ? 'mt-1' : '' }}">
                                            @if(is_array($message['content']))
                                                <pre class="whitespace-pre-wrap text-xs">{{ json_encode($message['content'], JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                {{ $message['content'] }}
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="text-xs opacity-75 mt-1">
                                        {{ \Carbon\Carbon::parse($message['timestamp'])->format('H:i:s') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Conversation Form -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>
        
        <!-- Available Tools -->
        @if(!empty($this->availableTools))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Available Tools
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->availableTools as $tool)
                            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $tool['name'] }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $tool['description'] ?? 'No description' }}
                                </p>
                                @if(!empty($tool['inputSchema']['properties']))
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Parameters:</p>
                                        <ul class="text-xs text-gray-600 dark:text-gray-400 ml-2">
                                            @foreach($tool['inputSchema']['properties'] as $param => $schema)
                                                <li>• {{ $param }} ({{ $schema['type'] ?? 'unknown' }})</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Listen for conversation updates
            window.addEventListener('conversation-updated', function() {
                const container = document.getElementById('conversation-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });

            // Auto-scroll on page load
            const container = document.getElementById('conversation-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }

            // Listen for real-time conversation updates via Echo
            if (window.Echo) {
                const userId = {{ auth()->id() ?? 1 }};
                window.Echo.private(`mcp-conversations.${userId}`)
                    .listen('.conversation.message', (e) => {
                        console.log('Real-time message received:', e);
                        // Livewire will handle the UI update via the #[On] listener
                        setTimeout(() => {
                            const container = document.getElementById('conversation-container');
                            if (container) {
                                container.scrollTop = container.scrollHeight;
                            }
                        }, 100);
                    });
            }
        });
    </script>
</x-filament-panels::page>