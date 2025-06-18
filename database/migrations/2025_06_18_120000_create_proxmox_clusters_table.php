<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('api_endpoint');
            $table->integer('api_port')->default(8006);
            $table->string('username');
            $table->text('password')->nullable(); // Encrypted
            $table->text('api_token')->nullable(); // Encrypted - preferred method
            $table->boolean('verify_tls')->default(false);
            $table->integer('timeout')->default(30);
            $table->enum('status', ['active', 'inactive', 'error', 'maintenance'])->default('inactive');
            $table->json('cluster_info')->nullable(); // Cluster status, version, etc.
            $table->json('total_resources')->nullable(); // Total CPU, memory, storage
            $table->json('used_resources')->nullable(); // Used CPU, memory, storage
            $table->json('configuration')->nullable(); // Additional settings
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_clusters');
    }
};
