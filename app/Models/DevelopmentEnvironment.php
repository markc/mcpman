<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class DevelopmentEnvironment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'proxmox_cluster_id',
        'name',
        'description',
        'template_name',
        'status',
        'environment_type',
        'template_config',
        'customizations',
        'network_vlan',
        'subnet_cidr',
        'dns_config',
        'total_cpu_cores',
        'total_memory_bytes',
        'total_storage_bytes',
        'estimated_cost_per_hour',
        'actual_cost_per_hour',
        'access_credentials',
        'security_policies',
        'public_access',
        'allowed_ips',
        'provisioned_at',
        'last_accessed_at',
        'expires_at',
        'auto_destroy_hours',
        'auto_start',
        'auto_stop',
        'backup_enabled',
        'backup_config',
        'last_backup_at',
        'snapshots',
        'git_repositories',
        'environment_variables',
        'exposed_ports',
        'ide_type',
        'ci_cd_enabled',
        'ci_cd_config',
        'usage_metrics',
        'cost_breakdown',
        'total_runtime_hours',
        'last_health_check',
        'project_name',
        'team_members',
        'tags',
        'notes',
        'last_error',
        'provisioning_log',
        'retry_count',
    ];

    protected $casts = [
        'template_config' => 'array',
        'customizations' => 'array',
        'dns_config' => 'array',
        'total_cpu_cores' => 'integer',
        'total_memory_bytes' => 'integer',
        'total_storage_bytes' => 'integer',
        'estimated_cost_per_hour' => 'decimal:4',
        'actual_cost_per_hour' => 'decimal:4',
        'access_credentials' => 'array',
        'security_policies' => 'array',
        'public_access' => 'boolean',
        'allowed_ips' => 'array',
        'provisioned_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_destroy_hours' => 'integer',
        'auto_start' => 'boolean',
        'auto_stop' => 'boolean',
        'backup_enabled' => 'boolean',
        'backup_config' => 'array',
        'last_backup_at' => 'datetime',
        'snapshots' => 'array',
        'git_repositories' => 'array',
        'environment_variables' => 'array',
        'exposed_ports' => 'array',
        'ci_cd_enabled' => 'boolean',
        'ci_cd_config' => 'array',
        'usage_metrics' => 'array',
        'cost_breakdown' => 'array',
        'total_runtime_hours' => 'integer',
        'last_health_check' => 'datetime',
        'team_members' => 'array',
        'tags' => 'array',
        'provisioning_log' => 'array',
        'retry_count' => 'integer',
    ];

    protected $hidden = [
        'access_credentials',
        'environment_variables',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
     * Encrypt access credentials before saving
     */
    public function setAccessCredentialsAttribute(?array $value): void
    {
        $this->attributes['access_credentials'] = $value ? Crypt::encryptString(json_encode($value)) : null;
    }

    /**
     * Decrypt access credentials when accessing
     */
    public function getAccessCredentialsAttribute(?string $value): ?array
    {
        return $value ? json_decode(Crypt::decryptString($value), true) : null;
    }

    /**
     * Encrypt environment variables before saving
     */
    public function setEnvironmentVariablesAttribute(?array $value): void
    {
        $this->attributes['environment_variables'] = $value ? Crypt::encryptString(json_encode($value)) : null;
    }

    /**
     * Decrypt environment variables when accessing
     */
    public function getEnvironmentVariablesAttribute(?string $value): ?array
    {
        return $value ? json_decode(Crypt::decryptString($value), true) : null;
    }

    /**
     * Check if environment is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if environment is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if environment is provisioning
     */
    public function isProvisioning(): bool
    {
        return $this->status === 'provisioning';
    }

    /**
     * Check if environment has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if environment is destroying
     */
    public function isDestroying(): bool
    {
        return $this->status === 'destroying';
    }

    /**
     * Check if environment should auto-destroy
     */
    public function shouldAutoDestroy(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get total number of resources
     */
    public function getTotalResources(): array
    {
        $vms = $this->virtualMachines()->count();
        $containers = $this->containers()->count();

        return [
            'vms' => $vms,
            'containers' => $containers,
            'total' => $vms + $containers,
        ];
    }

    /**
     * Get running resources
     */
    public function getRunningResources(): array
    {
        $runningVms = $this->virtualMachines()->where('status', 'running')->count();
        $runningContainers = $this->containers()->where('status', 'running')->count();

        return [
            'vms' => $runningVms,
            'containers' => $runningContainers,
            'total' => $runningVms + $runningContainers,
        ];
    }

    /**
     * Get actual resource usage
     */
    public function getActualResourceUsage(): array
    {
        $vms = $this->virtualMachines;
        $containers = $this->containers;

        $totalCpuCores = $vms->sum('cpu_cores') + $containers->sum('cpu_cores');
        $totalMemoryBytes = $vms->sum('memory_bytes') + $containers->sum('memory_bytes');
        $totalStorageBytes = $vms->sum('total_disk_bytes') + $containers->sum('disk_bytes');

        return [
            'cpu_cores' => $totalCpuCores,
            'memory_bytes' => $totalMemoryBytes,
            'storage_bytes' => $totalStorageBytes,
        ];
    }

    /**
     * Get resource utilization percentages
     */
    public function getResourceUtilization(): array
    {
        $vms = $this->virtualMachines()->running()->get();
        $containers = $this->containers()->running()->get();

        $totalCpuUsage = $vms->avg('cpu_usage_percent') ?? 0;
        $containerCpuUsage = $containers->avg('cpu_usage_percent') ?? 0;
        $avgCpuUsage = ($totalCpuUsage + $containerCpuUsage) / 2;

        $totalMemoryUsed = $vms->sum('memory_used_bytes') + $containers->sum('memory_used_bytes');
        $totalMemoryAllocated = $vms->sum('memory_bytes') + $containers->sum('memory_bytes');
        $memoryUtilization = $totalMemoryAllocated > 0 ? ($totalMemoryUsed / $totalMemoryAllocated) * 100 : 0;

        $totalDiskUsed = $vms->sum('disk_used_bytes') + $containers->sum('disk_used_bytes');
        $totalDiskAllocated = $vms->sum('total_disk_bytes') + $containers->sum('disk_bytes');
        $diskUtilization = $totalDiskAllocated > 0 ? ($totalDiskUsed / $totalDiskAllocated) * 100 : 0;

        return [
            'cpu' => round($avgCpuUsage, 2),
            'memory' => round($memoryUtilization, 2),
            'storage' => round($diskUtilization, 2),
        ];
    }

    /**
     * Calculate total runtime in hours
     */
    public function calculateTotalRuntime(): int
    {
        if (! $this->provisioned_at) {
            return 0;
        }

        $endTime = $this->status === 'running' ? now() : ($this->stopped_at ?? now());
        $startTime = $this->provisioned_at;

        return (int) $startTime->diffInHours($endTime);
    }

    /**
     * Get estimated monthly cost
     */
    public function getEstimatedMonthlyCost(): float
    {
        if (! $this->isRunning()) {
            return 0;
        }

        return ($this->actual_cost_per_hour ?? $this->estimated_cost_per_hour ?? 0) * 24 * 30;
    }

    /**
     * Get current cost for this billing period
     */
    public function getCurrentPeriodCost(): float
    {
        $runtime = $this->calculateTotalRuntime();

        return $runtime * ($this->actual_cost_per_hour ?? $this->estimated_cost_per_hour ?? 0);
    }

    /**
     * Get time until auto-destroy
     */
    public function getTimeUntilDestroy(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Overdue';
        }

        $diff = now()->diff($this->expires_at);

        if ($diff->days > 0) {
            return $diff->days.' days, '.$diff->h.' hours';
        } elseif ($diff->h > 0) {
            return $diff->h.' hours, '.$diff->i.' minutes';
        } else {
            return $diff->i.' minutes';
        }
    }

    /**
     * Get environment health score (0-100)
     */
    public function getHealthScore(): int
    {
        if (! $this->isRunning()) {
            return 0;
        }

        $score = 100;
        $resources = $this->getTotalResources();
        $runningResources = $this->getRunningResources();

        // Deduct points if not all resources are running
        if ($resources['total'] > 0) {
            $runningRatio = $runningResources['total'] / $resources['total'];
            $score = $score * $runningRatio;
        }

        // Deduct points for high resource utilization
        $utilization = $this->getResourceUtilization();
        $avgUtilization = ($utilization['cpu'] + $utilization['memory'] + $utilization['storage']) / 3;

        if ($avgUtilization > 90) {
            $score -= 20;
        } elseif ($avgUtilization > 80) {
            $score -= 10;
        }

        // Deduct points if environment hasn't been accessed recently
        if ($this->last_accessed_at && $this->last_accessed_at->diffInDays() > 7) {
            $score -= 15;
        }

        // Deduct points if health check is old
        if (! $this->last_health_check || $this->last_health_check->diffInHours() > 24) {
            $score -= 10;
        }

        return max(0, min(100, (int) $score));
    }

    /**
     * Get access URL based on exposed ports
     */
    public function getAccessUrls(): array
    {
        $urls = [];
        $exposedPorts = $this->exposed_ports ?? [];

        foreach ($exposedPorts as $service => $port) {
            if ($this->public_access && ! empty($this->virtualMachines)) {
                $primaryIp = $this->virtualMachines->first()?->primary_ip;
                if ($primaryIp) {
                    $protocol = in_array($port, [443, 8443]) ? 'https' : 'http';
                    $urls[$service] = "{$protocol}://{$primaryIp}:{$port}";
                }
            }
        }

        return $urls;
    }

    /**
     * Scope for active environments
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['running', 'provisioning']);
    }

    /**
     * Scope for running environments
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for environments that should auto-destroy
     */
    public function scopeShouldAutoDestroy($query)
    {
        return $query->where('expires_at', '<=', now())
            ->whereIn('status', ['running', 'stopped']);
    }

    /**
     * Scope for environments by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('environment_type', $type);
    }

    /**
     * Scope for environments by template
     */
    public function scopeUsingTemplate($query, string $template)
    {
        return $query->where('template_name', $template);
    }
}
