# Testing Guide

This guide covers testing strategies and procedures for Laravel Loop Filament with MCP integration, using the Pest PHP testing framework.

## Testing Setup

### Running Tests

```bash
# Run all tests
composer run test

# Or use Pest directly
php artisan test

# Run specific test files
php artisan test tests/Feature/McpServerTest.php
php artisan test tests/Unit/DatasetTest.php

# Run tests with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

### Test Database Configuration

Tests use an in-memory SQLite database by default. Configuration in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Unit Tests

### Model Testing

#### Dataset Model Test
```php
<?php

use App\Models\Dataset;
use App\Models\User;

test('dataset can be created with valid attributes', function () {
    $user = User::factory()->create();
    
    $dataset = Dataset::create([
        'name' => 'Test Dataset',
        'description' => 'Test description',
        'type' => 'json',
        'status' => 'active',
        'user_id' => $user->id,
    ]);
    
    expect($dataset->name)->toBe('Test Dataset');
    expect($dataset->slug)->toBe('test-dataset');
    expect($dataset->type)->toBe('json');
    expect($dataset->status)->toBe('active');
});

test('dataset generates slug automatically', function () {
    $user = User::factory()->create();
    
    $dataset = Dataset::create([
        'name' => 'My Special Dataset!',
        'type' => 'json',
        'user_id' => $user->id,
    ]);
    
    expect($dataset->slug)->toBe('my-special-dataset');
});

test('dataset has many documents relationship', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['user_id' => $user->id]);
    
    expect($dataset->documents())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
```

#### Document Model Test
```php
<?php

use App\Models\Document;
use App\Models\Dataset;
use App\Models\User;

test('document can be created with valid attributes', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['user_id' => $user->id]);
    
    $document = Document::create([
        'title' => 'Test Document',
        'content' => 'Test content',
        'type' => 'markdown',
        'dataset_id' => $dataset->id,
        'user_id' => $user->id,
    ]);
    
    expect($document->title)->toBe('Test Document');
    expect($document->slug)->toBe('test-document');
    expect($document->content)->toBe('Test content');
    expect($document->type)->toBe('markdown');
});

test('document belongs to dataset', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->create([
        'dataset_id' => $dataset->id,
        'user_id' => $user->id,
    ]);
    
    expect($document->dataset->id)->toBe($dataset->id);
});
```

#### API Key Model Test
```php
<?php

use App\Models\ApiKey;
use App\Models\User;

test('api key generates key with mcp prefix', function () {
    $user = User::factory()->create();
    
    $apiKey = ApiKey::create([
        'name' => 'Test Key',
        'permissions' => ['datasets' => 'read,write'],
        'rate_limits' => ['requests_per_minute' => '60'],
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    expect($apiKey->key)->toStartWith('mcp_');
    expect(strlen($apiKey->key))->toBeGreaterThan(10);
});

test('api key validates permissions format', function () {
    $user = User::factory()->create();
    
    $apiKey = ApiKey::factory()->create([
        'permissions' => [
            'datasets' => 'read,write',
            'documents' => 'read',
        ],
        'user_id' => $user->id,
    ]);
    
    expect($apiKey->permissions)->toBeArray();
    expect($apiKey->permissions['datasets'])->toBe('read,write');
});
```

### Service Testing

#### MCP Server Service Test
```php
<?php

use App\Services\McpServer;
use App\Models\Dataset;
use App\Models\User;

test('mcp server handles initialize request', function () {
    $server = new McpServer();
    
    $request = [
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'Test Client']
        ]
    ];
    
    $response = $server->handleRequest($request);
    
    expect($response['result']['protocolVersion'])->toBe('2024-11-05');
    expect($response['result']['capabilities'])->toHaveKey('tools');
    expect($response['result']['serverInfo']['name'])->toBe('Laravel-Filament-MCP');
});

test('mcp server lists available tools', function () {
    $server = new McpServer();
    
    $request = ['method' => 'tools/list'];
    $response = $server->handleRequest($request);
    
    expect($response['result']['tools'])->toBeArray();
    
    $toolNames = array_column($response['result']['tools'], 'name');
    expect($toolNames)->toContain('create_dataset');
    expect($toolNames)->toContain('list_datasets');
    expect($toolNames)->toContain('create_document');
    expect($toolNames)->toContain('search_documents');
});

test('mcp server creates dataset via tool', function () {
    $user = User::factory()->create();
    $server = new McpServer();
    
    $request = [
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_dataset',
            'arguments' => [
                'name' => 'API Test Dataset',
                'description' => 'Created via MCP',
                'type' => 'json'
            ]
        ]
    ];
    
    $response = $server->handleRequest($request);
    
    expect($response['result']['content'][0]['text'])->toContain('created successfully');
    
    $dataset = Dataset::where('name', 'API Test Dataset')->first();
    expect($dataset)->not->toBeNull();
    expect($dataset->type)->toBe('json');
});
```

## Feature Tests

### HTTP API Testing

#### MCP Controller Test
```php
<?php

use App\Models\ApiKey;
use App\Models\User;

test('mcp endpoint requires authentication', function () {
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => '1'
    ]);
    
    $response->assertStatus(401);
    $response->assertJson([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Invalid or missing API key'
        ]
    ]);
});

test('mcp endpoint accepts valid api key', function () {
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'key' => 'mcp_test_123',
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => '1'
    ], [
        'Authorization' => 'Bearer mcp_test_123',
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'jsonrpc',
        'result' => [
            'tools' => [
                '*' => ['name', 'description', 'inputSchema']
            ]
        ]
    ]);
});

test('mcp endpoint handles invalid json-rpc request', function () {
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'key' => 'mcp_test_123',
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    $response = $this->postJson('/api/mcp', [
        'invalid' => 'request'
    ], [
        'Authorization' => 'Bearer mcp_test_123',
    ]);
    
    $response->assertStatus(200);
    $response->assertJson([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32601,
            'message' => 'Unknown method'
        ]
    ]);
});
```

### Filament Resource Testing

#### Dataset Resource Test
```php
<?php

use App\Models\Dataset;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;

test('authenticated user can view datasets', function () {
    $user = User::factory()->create();
    $dataset = Dataset::factory()->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    $this->get('/admin/datasets')
        ->assertSuccessful()
        ->assertSee($dataset->name);
});

test('user can create dataset through form', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $response = $this->post('/admin/datasets', [
        'name' => 'New Dataset',
        'description' => 'Test description',
        'type' => 'json',
        'status' => 'active',
    ]);
    
    $this->assertDatabaseHas('datasets', [
        'name' => 'New Dataset',
        'slug' => 'new-dataset',
        'type' => 'json',
        'user_id' => $user->id,
    ]);
});
```

### MCP Client Testing

#### Client Service Test
```php
<?php

use App\Services\McpClient;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('mcp client connects via http transport', function () {
    $user = User::factory()->create();
    $connection = McpConnection::factory()->create([
        'transport_type' => 'http',
        'endpoint_url' => 'http://test-server/mcp',
        'user_id' => $user->id,
    ]);
    
    Http::fake([
        'test-server/mcp' => Http::response([
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => ['tools' => ['listChanged' => false]]
            ]
        ])
    ]);
    
    $client = new McpClient($connection);
    $result = $client->connect();
    
    expect($result)->toBeTrue();
    expect($connection->fresh()->status)->toBe('active');
});

test('mcp client handles connection failure', function () {
    $user = User::factory()->create();
    $connection = McpConnection::factory()->create([
        'transport_type' => 'http',
        'endpoint_url' => 'http://invalid-server/mcp',
        'user_id' => $user->id,
    ]);
    
    Http::fake([
        'invalid-server/mcp' => Http::response([], 500)
    ]);
    
    $client = new McpClient($connection);
    $result = $client->connect();
    
    expect($result)->toBeFalse();
    expect($connection->fresh()->status)->toBe('error');
});
```

## Integration Tests

### End-to-End MCP Testing

#### Full MCP Workflow Test
```php
<?php

use App\Models\User;
use App\Models\ApiKey;
use App\Models\Dataset;
use App\Models\Document;

test('complete mcp workflow from api to database', function () {
    // Setup
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'key' => 'mcp_integration_test',
        'permissions' => ['datasets' => 'read,write', 'documents' => 'read,write'],
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    // Create dataset via MCP API
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_dataset',
            'arguments' => [
                'name' => 'Integration Test Dataset',
                'description' => 'Created via integration test',
                'type' => 'json',
                'schema' => ['field1' => 'string', 'field2' => 'integer'],
            ]
        ],
        'id' => 'test-1'
    ], [
        'Authorization' => 'Bearer mcp_integration_test',
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('result.content.0.text', function ($text) {
        return str_contains($text, 'created successfully');
    });
    
    // Verify dataset in database
    $dataset = Dataset::where('name', 'Integration Test Dataset')->first();
    expect($dataset)->not->toBeNull();
    expect($dataset->type)->toBe('json');
    expect($dataset->schema)->toBe(['field1' => 'string', 'field2' => 'integer']);
    
    // Create document in the dataset
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_document',
            'arguments' => [
                'title' => 'Test Document',
                'content' => 'This is test content for integration testing',
                'type' => 'text',
                'dataset_id' => $dataset->id,
            ]
        ],
        'id' => 'test-2'
    ], [
        'Authorization' => 'Bearer mcp_integration_test',
    ]);
    
    $response->assertStatus(200);
    
    // Verify document in database
    $document = Document::where('title', 'Test Document')->first();
    expect($document)->not->toBeNull();
    expect($document->dataset_id)->toBe($dataset->id);
    expect($document->content)->toBe('This is test content for integration testing');
    
    // Search for the document
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => [
            'name' => 'search_documents',
            'arguments' => [
                'query' => 'integration testing',
                'dataset_id' => $dataset->id,
            ]
        ],
        'id' => 'test-3'
    ], [
        'Authorization' => 'Bearer mcp_integration_test',
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('result.content.0.text', function ($text) {
        return str_contains($text, 'Test Document');
    });
});
```

## Performance Testing

### Load Testing

#### API Endpoint Load Test
```php
<?php

use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Artisan;

test('mcp api handles concurrent requests', function () {
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'key' => 'mcp_load_test',
        'permissions' => ['datasets' => 'read,write'],
        'rate_limits' => ['requests_per_minute' => '1000'],
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    $startTime = microtime(true);
    $requests = 50;
    $responses = [];
    
    // Simulate concurrent requests (simplified)
    for ($i = 0; $i < $requests; $i++) {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => "load-test-{$i}"
        ], [
            'Authorization' => 'Bearer mcp_load_test',
        ]);
        
        $responses[] = $response->status();
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    // All requests should succeed
    expect(array_unique($responses))->toBe([200]);
    
    // Should complete within reasonable time (adjust as needed)
    expect($duration)->toBeLessThan(10.0);
    
    // Calculate requests per second
    $requestsPerSecond = $requests / $duration;
    expect($requestsPerSecond)->toBeGreaterThan(5);
});
```

### Memory Usage Testing

```php
<?php

test('large dataset creation does not exhaust memory', function () {
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'key' => 'mcp_memory_test',
        'permissions' => ['datasets' => 'read,write'],
        'is_active' => true,
        'user_id' => $user->id,
    ]);
    
    $initialMemory = memory_get_usage(true);
    
    // Create many datasets
    for ($i = 0; $i < 100; $i++) {
        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_dataset',
                'arguments' => [
                    'name' => "Memory Test Dataset {$i}",
                    'type' => 'json',
                ]
            ],
            'id' => "memory-test-{$i}"
        ], [
            'Authorization' => 'Bearer mcp_memory_test',
        ]);
    }
    
    $finalMemory = memory_get_usage(true);
    $memoryIncrease = $finalMemory - $initialMemory;
    
    // Memory increase should be reasonable (adjust based on your limits)
    expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024); // 50MB
});
```

## Test Factories

### Model Factories

Create comprehensive factories in `database/factories/`:

#### DatasetFactory.php
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatasetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['json', 'csv', 'xml', 'yaml', 'text']),
            'status' => $this->faker->randomElement(['active', 'archived', 'processing']),
            'schema' => [
                'id' => 'integer',
                'name' => 'string',
                'created_at' => 'datetime'
            ],
            'metadata' => [
                'version' => '1.0',
                'source' => 'factory'
            ],
            'user_id' => User::factory(),
        ];
    }
    
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
    
    public function json(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'json',
        ]);
    }
}
```

## Continuous Integration

### GitHub Actions Test Configuration

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [8.2, 8.3]
        
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite, dom, filter, gd, iconv, json, mbstring, pdo
    
    - name: Install Composer dependencies
      run: composer install --prefer-dist --no-interaction --no-progress
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
    
    - name: Install NPM dependencies
      run: npm ci
    
    - name: Build assets
      run: npm run build
    
    - name: Create database
      run: touch database/database.sqlite
    
    - name: Run migrations
      run: php artisan migrate --force
    
    - name: Execute tests
      run: php artisan test --coverage --min=80
```

## Test Data Seeding

### Test Seeders

Create specific seeders for testing in `database/seeders/`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Dataset;
use App\Models\Document;
use App\Models\ApiKey;
use App\Models\McpConnection;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        // Create test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        // Create test datasets
        $datasets = Dataset::factory(5)->create(['user_id' => $user->id]);
        
        // Create documents for each dataset
        foreach ($datasets as $dataset) {
            Document::factory(3)->create([
                'dataset_id' => $dataset->id,
                'user_id' => $user->id,
            ]);
        }
        
        // Create test API keys
        ApiKey::factory()->create([
            'name' => 'Test API Key',
            'key' => 'mcp_test_key_123',
            'permissions' => [
                'datasets' => 'read,write',
                'documents' => 'read,write',
                'connections' => 'read',
            ],
            'user_id' => $user->id,
        ]);
        
        // Create test MCP connections
        McpConnection::factory(2)->create(['user_id' => $user->id]);
    }
}
```

## Running Test Suites

### Test Categories

```bash
# Run all tests
php artisan test

# Run only unit tests
php artisan test tests/Unit

# Run only feature tests
php artisan test tests/Feature

# Run specific test groups
php artisan test --group=mcp
php artisan test --group=integration

# Run tests with specific filters
php artisan test --filter="dataset"
php artisan test --filter="McpServer"

# Generate coverage report
php artisan test --coverage-html coverage-report
```

### Test Debugging

```bash
# Run tests with verbose output
php artisan test --verbose

# Stop on first failure
php artisan test --stop-on-failure

# Run tests with debugging
php artisan test --debug

# Run specific test with dump output
php artisan test --filter="specific_test" --dump
```

This comprehensive testing guide ensures that all aspects of the Laravel Loop Filament MCP integration are thoroughly tested, from individual models to complete end-to-end workflows.