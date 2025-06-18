<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProxmoxNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'proxmox_cluster_id',
        'name',
        'ip_address',
        'port',
        'status',
        'node_type',
        'cpu_cores',
        'memory_bytes',
        'storage_bytes',
        'cpu_model',
        'storage_info',
        'network_info',
        'cpu_usage_percent',
        'memory_used_bytes',
        'storage_used_bytes',
        'load_average',
        'uptime_seconds',
        'capabilities',
        'pve_version',
        'kernel_version',
        'maintenance_mode',
        'last_health_check',
        'health_metrics',
        'last_error',
    ];

    protected $casts = [
        'port' => 'integer',
        'cpu_cores' => 'integer',
        'memory_bytes' => 'integer',
        'storage_bytes' => 'integer',
        'storage_info' => 'array',
        'network_info' => 'array',
        'cpu_usage_percent' => 'decimal:2',
        'memory_used_bytes' => 'integer',
        'storage_used_bytes' => 'integer',
        'load_average' => 'decimal:2',
        'uptime_seconds' => 'integer',
        'capabilities' => 'array',
        'maintenance_mode' => 'boolean',
        'last_health_check' => 'datetime',
        'health_metrics' => 'array',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ProxmoxCluster::class, 'proxmox_cluster_id');
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(ProxmoxVirtualMachine::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(ProxmoxContainer::class);
    }

    /**
     * Check if node is online and healthy
     */
    public function isHealthy(): bool
    {
        return $this->status === 'online' &&
               ! $this->maintenance_mode &&
               $this->last_health_check &&
               $this->last_health_check->diffInMinutes() < 15;
    }

    /**
     * Get memory utilization percentage
     */
    public function getMemoryUtilization(): float
    {
        if (! $this->memory_bytes || $this->memory_bytes === 0) {
            return 0;
        }

        return round(($this->memory_used_bytes ?? 0) / $this->memory_bytes * 100, 2);
    }

    /**
     * Get storage utilization percentage
     */
    public function getStorageUtilization(): float
    {
        if (! $this->storage_bytes || $this->storage_bytes === 0) {
            return 0;
        }

        return round(($this->storage_used_bytes ?? 0) / $this->storage_bytes * 100, 2);
    }

    /**
     * Get available memory in bytes
     */
    public function getAvailableMemory(): int
    {
        return max(0, ($this->memory_bytes ?? 0) - ($this->memory_used_bytes ?? 0));
    }

    /**
     * Get available storage in bytes
     */
    public function getAvailableStorage(): int
    {
        return max(0, ($this->storage_bytes ?? 0) - ($this->storage_used_bytes ?? 0));
    }

    /**
     * Get available CPU cores (estimation based on usage)
     */
    public function getAvailableCpuCores(): float
    {
        if (! $this->cpu_cores) {
            return 0;
        }

        $usagePercent = $this->cpu_usage_percent ?? 0;
        $usedCores = ($usagePercent / 100) * $this->cpu_cores;

        return max(0, $this->cpu_cores - $usedCores);
    }

    /**
     * Check if node can accommodate resource requirements
     */
    public function canAccommodate(int $cpuCores, int $memoryBytes, int $storageBytes): bool
    {
        return $this->isHealthy() &&
               $this->getAvailableCpuCores() >= $cpuCores &&
               $this->getAvailableMemory() >= $memoryBytes &&
               $this->getAvailableStorage() >= $storageBytes;
    }

    /**
     * Get node health score (0-100)
     */
    public function getHealthScore(): int
    {
        if (! $this->isHealthy()) {
            return 0;
        }

        $score = 100;

        // Deduct points for high CPU usage
        $cpuUsage = $this->cpu_usage_percent ?? 0;
        if ($cpuUsage > 90) {
            $score -= 30;
        } elseif ($cpuUsage > 80) {
            $score -= 15;
        } elseif ($cpuUsage > 70) {
            $score -= 5;
        }

        // Deduct points for high memory usage
        $memoryUsage = $this->getMemoryUtilization();
        if ($memoryUsage > 90) {
            $score -= 25;
        } elseif ($memoryUsage > 80) {
            $score -= 10;
        }

        // Deduct points for high storage usage
        $storageUsage = $this->getStorageUtilization();
        if ($storageUsage > 95) {
            $score -= 20;
        } elseif ($storageUsage > 85) {
            $score -= 10;
        }

        // Deduct points for high load average
        $loadAverage = $this->load_average ?? 0;
        $cpuCores = $this->cpu_cores ?? 1;
        $loadRatio = $loadAverage / $cpuCores;
        if ($loadRatio > 2) {
            $score -= 15;
        } elseif ($loadRatio > 1.5) {
            $score -= 10;
        }

        return max(0, min(100, (int) $score));
    }

    /**
     * Get total VMs on this node
     */
    public function getTotalVmsAttribute(): int
    {
        return $this->virtualMachines()->count();
    }

    /**
     * Get running VMs on this node
     */
    public function getRunningVmsAttribute(): int
    {
        return $this->virtualMachines()->where('status', 'running')->count();
    }

    /**
     * Get total containers on this node
     */
    public function getTotalContainersAttribute(): int
    {
        return $this->containers()->count();
    }

    /**
     * Get running containers on this node
     */
    public function getRunningContainersAttribute(): int
    {
        return $this->containers()->where('status', 'running')->count();
    }

    /**
     * Scope for healthy nodes
     */
    public function scopeHealthy($query)
    {
        return $query->where('status', 'online')
            ->where('maintenance_mode', false)
            ->where('last_health_check', '>', now()->subMinutes(15));
    }

    /**
     * Scope for nodes that can accommodate resources
     */
    public function scopeCanAccommodate($query, int $cpuCores, int $memoryBytes, int $storageBytes)
    {
        return $query->healthy()
            ->whereRaw('(cpu_cores - COALESCE(cpu_usage_percent, 0) / 100 * cpu_cores) >= ?', [$cpuCores])
            ->whereRaw('(memory_bytes - COALESCE(memory_used_bytes, 0)) >= ?', [$memoryBytes])
            ->whereRaw('(storage_bytes - COALESCE(storage_used_bytes, 0)) >= ?', [$storageBytes]);
    }
}
