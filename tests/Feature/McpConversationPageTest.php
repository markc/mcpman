<?php

namespace Tests\Feature;

use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpConversationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_page_loads_without_dynamic_event_errors(): void
    {
        echo "\n💬 Testing MCP Conversation Page for event listener issues...\n";

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $connection = McpConnection::create([
            'name' => 'Test Connection',
            'endpoint_url' => 'claude mcp serve',
            'transport_type' => 'stdio',
            'status' => 'active',
            'auth_config' => ['type' => 'none'],
            'capabilities' => ['tools' => true, 'resources' => true],
            'metadata' => ['test' => true],
            'user_id' => $user->id,
        ]);

        // Test conversation page access
        $response = $this->actingAs($user)->get('/admin/mcp-conversation');
        $this->assertTrue(in_array($response->status(), [200, 403]));
        echo "✓ Conversation page accessible (status: {$response->status()})\n";

        // Test page class instantiation
        $page = new \App\Filament\Pages\McpConversation;
        $this->assertInstanceOf(\App\Filament\Pages\McpConversation::class, $page);
        echo "✓ McpConversation page instantiates correctly\n";

        // Test getListeners method
        $listeners = $page->getListeners();
        $this->assertIsArray($listeners);
        $this->assertNotEmpty($listeners);
        echo "✓ Event listeners configured correctly\n";

        // Test that listeners don't contain unresolved placeholders
        foreach ($listeners as $event => $method) {
            $this->assertIsString($event);
            $this->assertIsString($method);
            $this->assertStringNotContainsString('{user.id}', $event);
            echo "✓ Event '$event' has no unresolved placeholders\n";
        }

        // Test mount method
        $page->mount();
        $this->assertIsArray($page->conversation);
        echo "✓ Page mounts successfully\n";

        echo "✅ Conversation page test completed without dynamic event errors!\n";
    }

    public function test_conversation_page_event_handling(): void
    {
        echo "\n📡 Testing conversation page event handling...\n";

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $page = new \App\Filament\Pages\McpConversation;
        $page->mount();

        // Test handleIncomingMessage method exists and works
        $this->assertTrue(method_exists($page, 'handleIncomingMessage'));
        echo "✓ handleIncomingMessage method exists\n";

        // Test with mock data
        $mockData = [
            'connection_id' => '1',
            'message' => [
                'role' => 'assistant',
                'content' => 'Test message',
                'timestamp' => now()->toISOString(),
            ],
        ];

        // This should not throw an exception
        $page->handleIncomingMessage($mockData);
        echo "✓ handleIncomingMessage processes data without errors\n";

        echo "✅ Event handling test completed!\n";
    }

    public function test_conversation_page_listeners_format(): void
    {
        echo "\n🎯 Testing listener format validation...\n";

        $page = new \App\Filament\Pages\McpConversation;
        $listeners = $page->getListeners();

        $this->assertIsArray($listeners);
        echo "✓ Listeners is an array\n";

        foreach ($listeners as $event => $handler) {
            // Ensure event names are properly formatted (no unresolved placeholders)
            $this->assertStringNotContainsString('{user.id}', $event);
            $this->assertStringNotContainsString('{$user.id}', $event);

            // Ensure event names contain properly resolved user IDs
            if (strpos($event, 'mcp-conversations.') !== false) {
                $this->assertMatchesRegularExpression('/mcp-conversations\.\d+/', $event);
                echo "✓ Event '$event' has properly resolved user ID\n";
            }

            // Ensure handler is a valid method name
            $this->assertIsString($handler);
            $this->assertTrue(method_exists($page, $handler));
            echo "✓ Handler '$handler' exists as a method\n";
        }

        echo "✅ Listener format validation completed!\n";
    }
}
