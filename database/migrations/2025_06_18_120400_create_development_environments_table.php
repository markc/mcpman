<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('template_name'); // Which template was used
            $table->enum('status', ['provisioning', 'running', 'stopped', 'failed', 'destroying'])->default('provisioning');
            $table->enum('environment_type', ['development', 'testing', 'staging', 'training'])->default('development');

            // Environment configuration
            $table->json('template_config'); // Original template configuration
            $table->json('customizations')->nullable(); // User customizations
            $table->string('network_vlan')->nullable();
            $table->string('subnet_cidr')->nullable();
            $table->json('dns_config')->nullable();

            // Resource allocation
            $table->integer('total_cpu_cores');
            $table->bigInteger('total_memory_bytes');
            $table->bigInteger('total_storage_bytes');
            $table->decimal('estimated_cost_per_hour', 10, 4)->nullable();
            $table->decimal('actual_cost_per_hour', 10, 4)->nullable();

            // Access and security
            $table->json('access_credentials')->nullable(); // SSH keys, passwords (encrypted)
            $table->json('security_policies')->nullable(); // Firewall rules, access controls
            $table->boolean('public_access')->default(false);
            $table->json('allowed_ips')->nullable(); // IP whitelist

            // Lifecycle management
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Auto-destroy date
            $table->integer('auto_destroy_hours')->nullable(); // Auto-destroy after hours
            $table->boolean('auto_start')->default(true);
            $table->boolean('auto_stop')->default(false);

            // Backup and snapshots
            $table->boolean('backup_enabled')->default(true);
            $table->json('backup_config')->nullable();
            $table->timestamp('last_backup_at')->nullable();
            $table->json('snapshots')->nullable();

            // Development workflow
            $table->json('git_repositories')->nullable(); // Git repos to clone
            $table->json('environment_variables')->nullable(); // Custom env vars
            $table->json('exposed_ports')->nullable(); // Port mappings
            $table->string('ide_type')->nullable(); // vscode, vim, etc.
            $table->boolean('ci_cd_enabled')->default(false);
            $table->json('ci_cd_config')->nullable();

            // Monitoring and analytics
            $table->json('usage_metrics')->nullable(); // CPU, memory, network usage
            $table->json('cost_breakdown')->nullable(); // Detailed cost analysis
            $table->integer('total_runtime_hours')->default(0);
            $table->timestamp('last_health_check')->nullable();

            // Project and team management
            $table->string('project_name')->nullable();
            $table->json('team_members')->nullable(); // Shared access users
            $table->json('tags')->nullable(); // Organization tags
            $table->text('notes')->nullable(); // User notes

            // Error handling
            $table->text('last_error')->nullable();
            $table->json('provisioning_log')->nullable(); // Detailed provisioning steps
            $table->integer('retry_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['proxmox_cluster_id', 'status']);
            $table->index(['template_name', 'status']);
            $table->index(['expires_at']);
            $table->index(['environment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_environments');
    }
};
