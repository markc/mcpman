<?php

namespace App\Models;

use App\Services\ProxmoxApiClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ProxmoxCluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'api_endpoint',
        'api_port',
        'username',
        'password',
        'api_token',
        'verify_tls',
        'timeout',
        'status',
        'cluster_info',
        'total_resources',
        'used_resources',
        'configuration',
        'last_seen_at',
        'last_error',
    ];

    protected $casts = [
        'api_port' => 'integer',
        'verify_tls' => 'boolean',
        'timeout' => 'integer',
        'cluster_info' => 'array',
        'total_resources' => 'array',
        'used_resources' => 'array',
        'configuration' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(ProxmoxNode::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(ProxmoxVirtualMachine::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(ProxmoxContainer::class);
    }

    public function developmentEnvironments(): HasMany
    {
        return $this->hasMany(DevelopmentEnvironment::class);
    }

    /**
     * Get API client instance for this cluster
     */
    public function getApiClient(): ProxmoxApiClient
    {
        $config = [
            'host' => $this->api_endpoint,
            'port' => $this->api_port,
            'username' => $this->username,
            'password' => $this->password ? Crypt::decryptString($this->password) : null,
            'api_token' => $this->api_token ? Crypt::decryptString($this->api_token) : null,
            'verify_tls' => $this->verify_tls,
            'timeout' => $this->timeout,
        ];

        return new ProxmoxApiClient($config);
    }

    /**
     * Encrypt password before saving
     */
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Encrypt API token before saving
     */
    public function setApiTokenAttribute(?string $value): void
    {
        $this->attributes['api_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt password when accessing
     */
    public function getPasswordAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Decrypt API token when accessing
     */
    public function getApiTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Check if cluster is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'active' &&
               $this->last_seen_at &&
               $this->last_seen_at->diffInMinutes() < 10;
    }

    /**
     * Get cluster resource utilization percentage
     */
    public function getResourceUtilization(): array
    {
        $total = $this->total_resources ?? [];
        $used = $this->used_resources ?? [];

        if (empty($total)) {
            return ['cpu' => 0, 'memory' => 0, 'storage' => 0];
        }

        return [
            'cpu' => $total['cpu'] > 0 ? round(($used['cpu'] ?? 0) / $total['cpu'] * 100, 2) : 0,
            'memory' => $total['memory'] > 0 ? round(($used['memory'] ?? 0) / $total['memory'] * 100, 2) : 0,
            'storage' => $total['storage'] > 0 ? round(($used['storage'] ?? 0) / $total['storage'] * 100, 2) : 0,
        ];
    }

    /**
     * Get total number of VMs across all nodes
     */
    public function getTotalVmsAttribute(): int
    {
        return $this->virtualMachines()->count();
    }

    /**
     * Get total number of containers across all nodes
     */
    public function getTotalContainersAttribute(): int
    {
        return $this->containers()->count();
    }

    /**
     * Get running VMs count
     */
    public function getRunningVmsAttribute(): int
    {
        return $this->virtualMachines()->where('status', 'running')->count();
    }

    /**
     * Get running containers count
     */
    public function getRunningContainersAttribute(): int
    {
        return $this->containers()->where('status', 'running')->count();
    }

    /**
     * Get online nodes count
     */
    public function getOnlineNodesAttribute(): int
    {
        return $this->nodes()->where('status', 'online')->count();
    }

    /**
     * Calculate estimated monthly cost
     */
    public function getEstimatedMonthlyCost(): float
    {
        $environments = $this->developmentEnvironments()->where('status', 'running')->get();
        $totalHourlyCost = $environments->sum('actual_cost_per_hour');

        return $totalHourlyCost * 24 * 30; // Assuming 30 days per month
    }

    /**
     * Get cluster health score (0-100)
     */
    public function getHealthScore(): int
    {
        $score = 100;

        // Deduct points for offline nodes
        $totalNodes = $this->nodes()->count();
        $onlineNodes = $this->getOnlineNodesAttribute();
        if ($totalNodes > 0) {
            $nodeHealth = ($onlineNodes / $totalNodes) * 30;
            $score = $score - 30 + $nodeHealth;
        }

        // Deduct points for high resource utilization
        $utilization = $this->getResourceUtilization();
        $avgUtilization = ($utilization['cpu'] + $utilization['memory'] + $utilization['storage']) / 3;
        if ($avgUtilization > 90) {
            $score -= 20;
        } elseif ($avgUtilization > 80) {
            $score -= 10;
        }

        // Deduct points if cluster hasn't been seen recently
        if (! $this->isOnline()) {
            $score -= 30;
        }

        return max(0, min(100, (int) $score));
    }

    /**
     * Scope for active clusters
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for online clusters
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'active')
            ->where('last_seen_at', '>', now()->subMinutes(10));
    }
}
