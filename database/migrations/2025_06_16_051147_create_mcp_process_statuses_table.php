<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mcp_process_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('process_name')->index();
            $table->json('command');
            $table->enum('status', ['starting', 'running', 'stopping', 'stopped', 'failed', 'died'])->index();
            $table->integer('pid')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->json('options')->nullable();
            $table->json('metrics')->nullable();
            $table->text('error_log')->nullable();
            $table->integer('restart_count')->default(0);
            $table->timestamps();

            // Indexes for performance
            $table->index(['process_name', 'status']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_process_statuses');
    }
};
