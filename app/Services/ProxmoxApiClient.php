<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxmoxApiClient
{
    private string $host;

    private int $port;

    private string $username;

    private ?string $password;

    private ?string $apiToken;

    private ?string $ticket;

    private ?string $csrfToken;

    private bool $verifyTls;

    private int $timeout;

    public function __construct(array $config)
    {
        $this->host = $config['host'];
        $this->port = $config['port'] ?? 8006;
        $this->username = $config['username'];
        $this->password = $config['password'] ?? null;
        $this->apiToken = $config['api_token'] ?? null;
        $this->verifyTls = $config['verify_tls'] ?? false;
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * Authenticate with Proxmox API using API token or username/password
     */
    public function authenticate(): bool
    {
        try {
            if ($this->apiToken) {
                // API Token authentication (recommended)
                return $this->authenticateWithToken();
            } else {
                // Ticket-based authentication
                return $this->authenticateWithTicket();
            }
        } catch (\Exception $e) {
            Log::error('Proxmox authentication failed', [
                'host' => $this->host,
                'username' => $this->username,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate using API token (preferred method)
     */
    private function authenticateWithToken(): bool
    {
        // API tokens don't require separate authentication
        // Just validate by making a test request
        try {
            $response = $this->makeRequest('GET', '/version');

            return $response !== null;
        } catch (\Exception $e) {
            Log::error('API token validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Authenticate using username/password to get ticket
     */
    private function authenticateWithTicket(): bool
    {
        $cacheKey = "proxmox_ticket_{$this->host}_{$this->username}";

        // Check for cached ticket
        $cachedTicket = Cache::get($cacheKey);
        if ($cachedTicket) {
            $this->ticket = $cachedTicket['ticket'];
            $this->csrfToken = $cachedTicket['csrf_token'];

            return true;
        }

        $response = Http::withOptions([
            'verify' => $this->verifyTls,
            'timeout' => $this->timeout,
        ])->post($this->getUrl('/access/ticket'), [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Authentication failed: '.$response->body());
        }

        $data = $response->json();

        if (! isset($data['data']['ticket'], $data['data']['CSRFPreventionToken'])) {
            throw new \Exception('Invalid authentication response');
        }

        $this->ticket = $data['data']['ticket'];
        $this->csrfToken = $data['data']['CSRFPreventionToken'];

        // Cache ticket for 1 hour (tickets are valid for 2 hours by default)
        Cache::put($cacheKey, [
            'ticket' => $this->ticket,
            'csrf_token' => $this->csrfToken,
        ], 3600);

        return true;
    }

    /**
     * Make authenticated HTTP request to Proxmox API
     */
    public function makeRequest(string $method, string $endpoint, array $data = []): ?array
    {
        if (! $this->isAuthenticated()) {
            if (! $this->authenticate()) {
                throw new \Exception('Authentication required');
            }
        }

        $url = $this->getUrl($endpoint);
        $headers = $this->getAuthHeaders($method);

        Log::debug('Proxmox API request', [
            'method' => $method,
            'url' => $url,
            'data' => $data,
        ]);

        try {
            $httpClient = Http::withOptions([
                'verify' => $this->verifyTls,
                'timeout' => $this->timeout,
            ])->withHeaders($headers);

            $response = match (strtoupper($method)) {
                'GET' => $httpClient->get($url, $data),
                'POST' => $httpClient->post($url, $data),
                'PUT' => $httpClient->put($url, $data),
                'DELETE' => $httpClient->delete($url, $data),
                default => throw new \Exception("Unsupported HTTP method: {$method}")
            };

            if (! $response->successful()) {
                $this->handleApiError($response);

                return null;
            }

            $responseData = $response->json();

            // Proxmox API returns data in 'data' field
            return $responseData['data'] ?? $responseData;

        } catch (\Exception $e) {
            Log::error('Proxmox API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get cluster status
     */
    public function getClusterStatus(): array
    {
        return $this->makeRequest('GET', '/cluster/status') ?? [];
    }

    /**
     * Get cluster nodes
     */
    public function getNodes(): array
    {
        return $this->makeRequest('GET', '/nodes') ?? [];
    }

    /**
     * Get node status
     */
    public function getNodeStatus(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/status") ?? [];
    }

    /**
     * Get node resource usage
     */
    public function getNodeResources(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/rrddata", [
            'timeframe' => 'hour',
        ]) ?? [];
    }

    /**
     * Get VMs on a node
     */
    public function getVMs(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/qemu") ?? [];
    }

    /**
     * Get containers on a node
     */
    public function getContainers(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/lxc") ?? [];
    }

    /**
     * Create a VM
     */
    public function createVM(string $node, array $config): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/qemu", $config) ?? [];
    }

    /**
     * Create a container
     */
    public function createContainer(string $node, array $config): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/lxc", $config) ?? [];
    }

    /**
     * Clone a VM
     */
    public function cloneVM(string $node, string $vmid, array $config): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/qemu/{$vmid}/clone", $config) ?? [];
    }

    /**
     * Clone a container
     */
    public function cloneContainer(string $node, string $ctid, array $config): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/lxc/{$ctid}/clone", $config) ?? [];
    }

    /**
     * Start a VM
     */
    public function startVM(string $node, string $vmid): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/qemu/{$vmid}/status/start") ?? [];
    }

    /**
     * Start a container
     */
    public function startContainer(string $node, string $ctid): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/lxc/{$ctid}/status/start") ?? [];
    }

    /**
     * Stop a VM
     */
    public function stopVM(string $node, string $vmid): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/qemu/{$vmid}/status/stop") ?? [];
    }

    /**
     * Stop a container
     */
    public function stopContainer(string $node, string $ctid): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/lxc/{$ctid}/status/stop") ?? [];
    }

    /**
     * Get VM status
     */
    public function getVMStatus(string $node, string $vmid): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/qemu/{$vmid}/status/current") ?? [];
    }

    /**
     * Get container status
     */
    public function getContainerStatus(string $node, string $ctid): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/lxc/{$ctid}/status/current") ?? [];
    }

    /**
     * Migrate VM to another node
     */
    public function migrateVM(string $node, string $vmid, string $target, array $options = []): array
    {
        $config = array_merge(['target' => $target], $options);

        return $this->makeRequest('POST', "/nodes/{$node}/qemu/{$vmid}/migrate", $config) ?? [];
    }

    /**
     * Migrate container to another node
     */
    public function migrateContainer(string $node, string $ctid, string $target, array $options = []): array
    {
        $config = array_merge(['target' => $target], $options);

        return $this->makeRequest('POST', "/nodes/{$node}/lxc/{$ctid}/migrate", $config) ?? [];
    }

    /**
     * Create backup
     */
    public function createBackup(string $node, array $config): array
    {
        return $this->makeRequest('POST', "/nodes/{$node}/vzdump", $config) ?? [];
    }

    /**
     * Get storage information
     */
    public function getStorage(): array
    {
        return $this->makeRequest('GET', '/storage') ?? [];
    }

    /**
     * Get storage content
     */
    public function getStorageContent(string $node, string $storage): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/storage/{$storage}/content") ?? [];
    }

    /**
     * Get network configuration
     */
    public function getNetworkConfig(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/network") ?? [];
    }

    /**
     * Get firewall rules
     */
    public function getFirewallRules(string $node): array
    {
        return $this->makeRequest('GET', "/nodes/{$node}/firewall/rules") ?? [];
    }

    /**
     * Get API version
     */
    public function getVersion(): array
    {
        return $this->makeRequest('GET', '/version') ?? [];
    }

    /**
     * Get cluster resources (overview)
     */
    public function getClusterResources(): array
    {
        return $this->makeRequest('GET', '/cluster/resources') ?? [];
    }

    /**
     * Get next available VM ID
     */
    public function getNextVmId(): int
    {
        $response = $this->makeRequest('GET', '/cluster/nextid');

        return (int) ($response ?? 100);
    }

    /**
     * Check if authenticated
     */
    private function isAuthenticated(): bool
    {
        return $this->apiToken !== null || ($this->ticket !== null && $this->csrfToken !== null);
    }

    /**
     * Get full API URL
     */
    private function getUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');

        return "https://{$this->host}:{$this->port}/api2/json/{$endpoint}";
    }

    /**
     * Get authentication headers
     */
    private function getAuthHeaders(string $method): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiToken) {
            // API Token authentication
            $headers['Authorization'] = "PVEAPIToken={$this->apiToken}";
        } elseif ($this->ticket && $this->csrfToken) {
            // Ticket authentication
            $headers['Cookie'] = "PVEAuthCookie={$this->ticket}";

            // CSRF token required for write operations
            if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'])) {
                $headers['CSRFPreventionToken'] = $this->csrfToken;
            }
        }

        return $headers;
    }

    /**
     * Handle API errors
     */
    private function handleApiError($response): void
    {
        $statusCode = $response->status();
        $body = $response->body();

        switch ($statusCode) {
            case 401:
                // Clear cached authentication
                $cacheKey = "proxmox_ticket_{$this->host}_{$this->username}";
                Cache::forget($cacheKey);
                $this->ticket = null;
                $this->csrfToken = null;
                throw new \Exception('Authentication failed or expired');
            case 403:
                throw new \Exception('Insufficient privileges for this operation');
            case 404:
                throw new \Exception('Resource not found');
            case 500:
                throw new \Exception('Proxmox server error: '.$body);
            default:
                throw new \Exception("Proxmox API error ({$statusCode}): ".$body);
        }
    }
}
