<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpProcessStatus extends Model
{
    protected $fillable = [
        'process_name',
        'command',
        'status',
        'pid',
        'started_at',
        'stopped_at',
        'last_health_check',
        'options',
        'metrics',
        'error_log',
        'restart_count',
    ];

    protected $casts = [
        'command' => 'array',
        'options' => 'array',
        'metrics' => 'array',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_health_check' => 'datetime',
    ];

    /**
     * Get the user who owns this process (if applicable)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for running processes
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for failed processes
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'died']);
    }

    /**
     * Get uptime in human readable format
     */
    public function getUptimeAttribute(): ?string
    {
        if ($this->status !== 'running' || ! $this->started_at) {
            return null;
        }

        return $this->started_at->diffForHumans(null, true);
    }

    /**
     * Check if process needs health check
     */
    public function needsHealthCheck(): bool
    {
        if ($this->status !== 'running') {
            return false;
        }

        if (! $this->last_health_check) {
            return true;
        }

        // Check every 30 seconds
        return $this->last_health_check->diffInSeconds() > 30;
    }

    /**
     * Get process resource usage if available
     */
    public function getResourceUsage(): array
    {
        if (! $this->pid || $this->status !== 'running') {
            return [];
        }

        try {
            // Get process info from /proc filesystem
            $statFile = "/proc/{$this->pid}/stat";
            if (file_exists($statFile)) {
                $stat = file_get_contents($statFile);
                $parts = explode(' ', $stat);

                return [
                    'cpu_time' => isset($parts[13], $parts[14]) ? (int) $parts[13] + (int) $parts[14] : 0,
                    'memory_mb' => isset($parts[23]) ? round((int) $parts[23] / 1024 / 1024, 2) : 0,
                    'threads' => isset($parts[19]) ? (int) $parts[19] : 0,
                ];
            }
        } catch (\Exception $e) {
            // Ignore errors in resource monitoring
        }

        return [];
    }

    /**
     * Update health check timestamp
     */
    public function updateHealthCheck(array $metrics = []): void
    {
        $this->update([
            'last_health_check' => now(),
            'metrics' => array_merge($this->metrics ?? [], $metrics),
        ]);
    }
}
