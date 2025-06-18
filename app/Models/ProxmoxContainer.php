<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxmoxContainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'proxmox_cluster_id',
        'proxmox_node_id',
        'development_environment_id',
        'ctid',
        'name',
        'description',
        'status',
        'type',
        'os_template',
        'os_type',
        'cpu_cores',
        'memory_bytes',
        'swap_bytes',
        'disk_bytes',
        'privileged',
        'nesting',
        'features',
        'arch',
        'storage_backend',
        'rootfs_volume',
        'mount_points',
        'network_interfaces',
        'primary_ip',
        'gateway',
        'dns_servers',
        'cpu_usage_percent',
        'memory_used_bytes',
        'disk_used_bytes',
        'network_in_bytes',
        'network_out_bytes',
        'uptime_seconds',
        'backup_enabled',
        'backup_schedule',
        'last_backup_at',
        'snapshots',
        'cgroup_limits',
        'capabilities',
        'console_mode',
        'template_id',
        'clone_config',
        'is_template',
        'started_at',
        'stopped_at',
        'last_seen_at',
        'last_error',
        'tags',
    ];

    protected $casts = [
        'cpu_cores' => 'integer',
        'memory_bytes' => 'integer',
        'swap_bytes' => 'integer',
        'disk_bytes' => 'integer',
        'privileged' => 'boolean',
        'nesting' => 'boolean',
        'features' => 'array',
        'mount_points' => 'array',
        'network_interfaces' => 'array',
        'dns_servers' => 'array',
        'cpu_usage_percent' => 'decimal:2',
        'memory_used_bytes' => 'integer',
        'disk_used_bytes' => 'integer',
        'network_in_bytes' => 'integer',
        'network_out_bytes' => 'integer',
        'uptime_seconds' => 'integer',
        'backup_enabled' => 'boolean',
        'backup_schedule' => 'array',
        'last_backup_at' => 'datetime',
        'snapshots' => 'array',
        'cgroup_limits' => 'array',
        'capabilities' => 'array',
        'clone_config' => 'array',
        'is_template' => 'boolean',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'tags' => 'array',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ProxmoxCluster::class, 'proxmox_cluster_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ProxmoxNode::class, 'proxmox_node_id');
    }

    public function developmentEnvironment(): BelongsTo
    {
        return $this->belongsTo(DevelopmentEnvironment::class);
    }

    /**
     * Check if container is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if container is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if container is a template
     */
    public function isTemplate(): bool
    {
        return $this->is_template || $this->type === 'template';
    }

    /**
     * Check if container is privileged
     */
    public function isPrivileged(): bool
    {
        return $this->privileged;
    }

    /**
     * Check if container supports nesting (Docker in LXC)
     */
    public function supportsNesting(): bool
    {
        return $this->nesting;
    }

    /**
     * Get memory utilization percentage (including swap)
     */
    public function getMemoryUtilization(): float
    {
        $totalMemory = $this->memory_bytes + ($this->swap_bytes ?? 0);

        if (! $totalMemory || $totalMemory === 0) {
            return 0;
        }

        return round(($this->memory_used_bytes ?? 0) / $totalMemory * 100, 2);
    }

    /**
     * Get disk utilization percentage
     */
    public function getDiskUtilization(): float
    {
        if (! $this->disk_bytes || $this->disk_bytes === 0) {
            return 0;
        }

        return round(($this->disk_used_bytes ?? 0) / $this->disk_bytes * 100, 2);
    }

    /**
     * Get available memory in bytes
     */
    public function getAvailableMemory(): int
    {
        return max(0, $this->memory_bytes - ($this->memory_used_bytes ?? 0));
    }

    /**
     * Get available disk space in bytes
     */
    public function getAvailableDisk(): int
    {
        return max(0, $this->disk_bytes - ($this->disk_used_bytes ?? 0));
    }

    /**
     * Get formatted uptime
     */
    public function getFormattedUptime(): string
    {
        if (! $this->uptime_seconds) {
            return 'N/A';
        }

        $days = floor($this->uptime_seconds / 86400);
        $hours = floor(($this->uptime_seconds % 86400) / 3600);
        $minutes = floor(($this->uptime_seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get network usage in human readable format
     */
    public function getFormattedNetworkUsage(): array
    {
        return [
            'in' => $this->formatBytes($this->network_in_bytes ?? 0),
            'out' => $this->formatBytes($this->network_out_bytes ?? 0),
        ];
    }

    /**
     * Get enabled features as formatted string
     */
    public function getEnabledFeatures(): string
    {
        $features = $this->features ?? [];

        if (empty($features)) {
            return 'None';
        }

        return implode(', ', array_keys(array_filter($features)));
    }

    /**
     * Get security level based on privileges and capabilities
     */
    public function getSecurityLevel(): string
    {
        if ($this->privileged) {
            return 'Low';
        }

        $capabilities = $this->capabilities ?? [];
        $riskyCapabilities = ['SYS_ADMIN', 'SYS_MODULE', 'SYS_RAWIO'];

        $hasRiskyCapabilities = ! empty(array_intersect($capabilities, $riskyCapabilities));

        if ($hasRiskyCapabilities) {
            return 'Medium';
        }

        return 'High';
    }

    /**
     * Get total number of snapshots
     */
    public function getSnapshotCount(): int
    {
        return count($this->snapshots ?? []);
    }

    /**
     * Get latest snapshot
     */
    public function getLatestSnapshot(): ?array
    {
        $snapshots = $this->snapshots ?? [];

        if (empty($snapshots)) {
            return null;
        }

        // Sort by creation time and get latest
        usort($snapshots, function ($a, $b) {
            return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
        });

        return $snapshots[0];
    }

    /**
     * Check if backup is overdue
     */
    public function isBackupOverdue(): bool
    {
        if (! $this->backup_enabled || ! $this->backup_schedule) {
            return false;
        }

        $schedule = $this->backup_schedule;
        $frequency = $schedule['frequency'] ?? 'daily';
        $lastBackup = $this->last_backup_at;

        if (! $lastBackup) {
            return true;
        }

        $overdueThreshold = match ($frequency) {
            'hourly' => now()->subHours(2),
            'daily' => now()->subDays(2),
            'weekly' => now()->subWeeks(2),
            'monthly' => now()->subMonths(2),
            default => now()->subDays(2),
        };

        return $lastBackup->lt($overdueThreshold);
    }

    /**
     * Get container health score (0-100)
     */
    public function getHealthScore(): int
    {
        if (! $this->isRunning()) {
            return $this->status === 'stopped' ? 80 : 0;
        }

        $score = 100;

        // Deduct points for high CPU usage
        $cpuUsage = $this->cpu_usage_percent ?? 0;
        if ($cpuUsage > 95) {
            $score -= 25;
        } elseif ($cpuUsage > 85) {
            $score -= 15;
        } elseif ($cpuUsage > 75) {
            $score -= 5;
        }

        // Deduct points for high memory usage
        $memoryUsage = $this->getMemoryUtilization();
        if ($memoryUsage > 95) {
            $score -= 20;
        } elseif ($memoryUsage > 85) {
            $score -= 10;
        }

        // Deduct points for high disk usage
        $diskUsage = $this->getDiskUtilization();
        if ($diskUsage > 95) {
            $score -= 15;
        } elseif ($diskUsage > 85) {
            $score -= 5;
        }

        // Deduct points for overdue backups
        if ($this->isBackupOverdue()) {
            $score -= 10;
        }

        // Deduct points if not seen recently
        if ($this->last_seen_at && $this->last_seen_at->diffInMinutes() > 30) {
            $score -= 20;
        }

        return max(0, min(100, (int) $score));
    }

    /**
     * Get estimated monthly cost
     */
    public function getEstimatedMonthlyCost(): float
    {
        if ($this->developmentEnvironment) {
            return $this->developmentEnvironment->actual_cost_per_hour * 24 * 30;
        }

        // Basic cost calculation based on resources (containers are cheaper than VMs)
        $cpuCost = $this->cpu_cores * 0.03; // $0.03 per core per hour (cheaper than VMs)
        $memoryCost = ($this->memory_bytes / 1024 / 1024 / 1024) * 0.008; // $0.008 per GB per hour
        $diskCost = ($this->disk_bytes / 1024 / 1024 / 1024) * 0.0008; // $0.0008 per GB per hour

        $hourlyCost = $cpuCost + $memoryCost + $diskCost;

        return $this->isRunning() ? $hourlyCost * 24 * 30 : 0;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Scope for running containers
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for stopped containers
     */
    public function scopeStopped($query)
    {
        return $query->where('status', 'stopped');
    }

    /**
     * Scope for templates
     */
    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    /**
     * Scope for containers (non-templates)
     */
    public function scopeContainers($query)
    {
        return $query->where('is_template', false);
    }

    /**
     * Scope for privileged containers
     */
    public function scopePrivileged($query)
    {
        return $query->where('privileged', true);
    }

    /**
     * Scope for unprivileged containers
     */
    public function scopeUnprivileged($query)
    {
        return $query->where('privileged', false);
    }
}
