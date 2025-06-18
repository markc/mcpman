<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_virtual_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxmox_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('development_environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vmid'); // Proxmox VM ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['running', 'stopped', 'paused', 'suspended', 'unknown'])->default('unknown');
            $table->enum('type', ['vm', 'template'])->default('vm');

            // VM Configuration
            $table->string('os_type')->nullable(); // Linux, Windows, etc.
            $table->integer('cpu_cores');
            $table->bigInteger('memory_bytes');
            $table->string('cpu_type')->nullable(); // host, kvm64, etc.
            $table->boolean('cpu_numa')->default(false);
            $table->json('cpu_flags')->nullable();

            // Storage configuration
            $table->json('disks')->nullable(); // Disk configuration array
            $table->bigInteger('total_disk_bytes')->nullable();
            $table->string('boot_disk')->nullable();

            // Network configuration
            $table->json('network_interfaces')->nullable();
            $table->string('primary_ip')->nullable();
            $table->string('mac_address')->nullable();

            // Performance and monitoring
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->bigInteger('memory_used_bytes')->nullable();
            $table->bigInteger('disk_used_bytes')->nullable();
            $table->bigInteger('network_in_bytes')->nullable();
            $table->bigInteger('network_out_bytes')->nullable();
            $table->integer('uptime_seconds')->nullable();

            // Backup and snapshots
            $table->boolean('backup_enabled')->default(true);
            $table->json('backup_schedule')->nullable();
            $table->timestamp('last_backup_at')->nullable();
            $table->json('snapshots')->nullable();

            // High availability
            $table->boolean('ha_enabled')->default(false);
            $table->integer('ha_priority')->nullable();
            $table->string('ha_group')->nullable();

            // Template and cloning
            $table->string('template_id')->nullable(); // Source template
            $table->json('clone_config')->nullable(); // Clone configuration
            $table->boolean('is_template')->default(false);

            // Lifecycle management
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('tags')->nullable(); // VM tags for organization

            $table->timestamps();

            $table->index(['proxmox_cluster_id', 'status']);
            $table->index(['proxmox_node_id', 'status']);
            $table->index(['development_environment_id']);
            $table->unique(['proxmox_cluster_id', 'vmid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_virtual_machines');
    }
};
