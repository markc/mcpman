<?php

namespace Tests\Browser;

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class McpPagesTest extends PantherTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createPantherClient();
    }

    public function test_mcp_dashboard_loads_correctly(): void
    {
        $crawler = $this->client->request('GET', 'http://localhost:8000/admin');

        // Save page source for debugging
        file_put_contents('debug_dashboard.html', $this->client->getPageSource());

        $this->assertStringContainsString('Dashboard', $this->client->getPageSource());
        $this->assertStringContainsString('Total Connections', $this->client->getPageSource());
    }

    public function test_mcp_conversation_loads_correctly(): void
    {
        $crawler = $this->client->request('GET', 'http://localhost:8000/admin/mcp-conversation');

        $this->assertStringContainsString('MCP Conversation', $this->client->getPageSource());
        $this->assertStringContainsString('Conversation', $this->client->getPageSource());
    }
}
