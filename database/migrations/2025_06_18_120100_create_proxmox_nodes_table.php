<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxmox_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Node name in Proxmox
            $table->string('ip_address');
            $table->integer('port')->default(22); // SSH port
            $table->enum('status', ['online', 'offline', 'unknown', 'maintenance'])->default('unknown');
            $table->enum('node_type', ['master', 'worker', 'storage'])->default('worker');

            // Resource specifications
            $table->integer('cpu_cores')->nullable();
            $table->bigInteger('memory_bytes')->nullable(); // Total memory in bytes
            $table->bigInteger('storage_bytes')->nullable(); // Total storage in bytes
            $table->string('cpu_model')->nullable();
            $table->json('storage_info')->nullable(); // Storage pools, types, etc.
            $table->json('network_info')->nullable(); // Network interfaces, VLANs

            // Current utilization
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->bigInteger('memory_used_bytes')->nullable();
            $table->bigInteger('storage_used_bytes')->nullable();
            $table->decimal('load_average', 8, 2)->nullable();
            $table->integer('uptime_seconds')->nullable();

            // Capabilities and features
            $table->json('capabilities')->nullable(); // Supported features
            $table->string('pve_version')->nullable(); // Proxmox VE version
            $table->string('kernel_version')->nullable();

            // Maintenance and health
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamp('last_health_check')->nullable();
            $table->json('health_metrics')->nullable(); // Detailed health data
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['proxmox_cluster_id', 'status']);
            $table->index(['status', 'maintenance_mode']);
            $table->unique(['proxmox_cluster_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_nodes');
    }
};
