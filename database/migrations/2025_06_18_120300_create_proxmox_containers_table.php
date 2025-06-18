<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxmox_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('development_environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ctid'); // Proxmox Container ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['running', 'stopped', 'paused', 'unknown'])->default('unknown');
            $table->enum('type', ['container', 'template'])->default('container');

            // Container Configuration
            $table->string('os_template'); // Template used to create container
            $table->string('os_type')->nullable(); // ubuntu, debian, centos, etc.
            $table->integer('cpu_cores');
            $table->bigInteger('memory_bytes');
            $table->bigInteger('swap_bytes')->nullable();
            $table->bigInteger('disk_bytes');

            // Container features
            $table->boolean('privileged')->default(false);
            $table->boolean('nesting')->default(false); // Docker in LXC
            $table->json('features')->nullable(); // keyctl, fuse, etc.
            $table->string('arch')->default('amd64');

            // Storage configuration
            $table->string('storage_backend'); // local, ceph, nfs, etc.
            $table->string('rootfs_volume')->nullable();
            $table->json('mount_points')->nullable(); // Additional mount points

            // Network configuration
            $table->json('network_interfaces')->nullable();
            $table->string('primary_ip')->nullable();
            $table->string('gateway')->nullable();
            $table->json('dns_servers')->nullable();

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

            // Security and limits
            $table->json('cgroup_limits')->nullable(); // CPU, memory limits
            $table->json('capabilities')->nullable(); // Linux capabilities
            $table->string('console_mode')->default('tty'); // shell, console

            // Template and cloning
            $table->string('template_id')->nullable(); // Source template
            $table->json('clone_config')->nullable(); // Clone configuration
            $table->boolean('is_template')->default(false);

            // Lifecycle management
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('tags')->nullable(); // Container tags for organization

            $table->timestamps();

            $table->index(['proxmox_cluster_id', 'status']);
            $table->index(['proxmox_node_id', 'status']);
            $table->index(['development_environment_id']);
            $table->unique(['proxmox_cluster_id', 'ctid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_containers');
    }
};
