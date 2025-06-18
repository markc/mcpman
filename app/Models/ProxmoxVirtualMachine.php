<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxmoxVirtualMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'proxmox_cluster_id',
        'proxmox_node_id',
        'development_environment_id',
        'vmid',
        'name',
        'description',
        'status',
        'type',
        'os_type',
        'cpu_cores',
        'memory_bytes',
        'cpu_type',
        'cpu_numa',
        'cpu_flags',
        'disks',
        'total_disk_bytes',
        'boot_disk',
        'network_interfaces',
        'primary_ip',
        'mac_address',
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
        'ha_enabled',
        'ha_priority',
        'ha_group',
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
        'cpu_numa' => 'boolean',
        'cpu_flags' => 'array',
        'disks' => 'array',
        'total_disk_bytes' => 'integer',
        'network_interfaces' => 'array',
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
        'ha_enabled' => 'boolean',
        'ha_priority' => 'integer',
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
     * Check if VM is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if VM is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if VM is a template
     */
    public function isTemplate(): bool
    {
        return $this->is_template || $this->type === 'template';
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
     * Get disk utilization percentage
     */
    public function getDiskUtilization(): float
    {
        if (! $this->total_disk_bytes || $this->total_disk_bytes === 0) {
            return 0;
        }

        return round(($this->disk_used_bytes ?? 0) / $this->total_disk_bytes * 100, 2);
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
     * Get VM health score (0-100)
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

        // Basic cost calculation based on resources
        $cpuCost = $this->cpu_cores * 0.05; // $0.05 per core per hour
        $memoryCost = ($this->memory_bytes / 1024 / 1024 / 1024) * 0.01; // $0.01 per GB per hour
        $diskCost = ($this->total_disk_bytes / 1024 / 1024 / 1024) * 0.001; // $0.001 per GB per hour

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
     * Scope for running VMs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for stopped VMs
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
     * Scope for VMs (non-templates)
     */
    public function scopeVms($query)
    {
        return $query->where('is_template', false);
    }
}
