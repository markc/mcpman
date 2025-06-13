<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Conversation Display -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Conversation
                </h3>
            </div>
            
            <div class="p-4 max-h-96 overflow-y-auto space-y-4" id="conversation-container">
                @if(empty($this->conversation))
                    <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <p class="mt-2">No messages yet. Start a conversation with Claude Code!</p>
                    </div>
                @else
                    @foreach($this->conversation as $message)
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                                @if($message['role'] === 'user')
                                    bg-blue-500 text-white
                                @elseif($message['role'] === 'assistant')
                                    bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100
                                @elseif($message['role'] === 'tool_call')
                                    bg-yellow-100 dark:bg-yellow-900 text-yellow-900 dark:text-yellow-100 border border-yellow-300
                                @elseif($message['role'] === 'tool_result')
                                    bg-green-100 dark:bg-green-900 text-green-900 dark:text-green-100 border border-green-300
                                @else
                                    bg-red-100 dark:bg-red-900 text-red-900 dark:text-red-100 border border-red-300
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
        
        <!-- Conversation Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4">
                {{ $this->conversationForm }}
            </div>
        </div>
        
        <!-- Available Tools -->
        @if(!empty($this->availableTools))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Available Tools
                    </h3>
                </div>
                
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->availableTools as $tool)
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
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
        // Auto-scroll conversation to bottom
        document.addEventListener('livewire:navigated', () => {
            const container = document.getElementById('conversation-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
        
        // Also scroll when new messages are added
        Livewire.hook('morph.updated', ({ component, cleanup }) => {
            const container = document.getElementById('conversation-container');
            if (container) {
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 100);
            }
        });
    </script>
</x-filament-panels::page>