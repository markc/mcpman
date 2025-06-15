<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('connection_id')->nullable()->constrained('mcp_connections')->onDelete('set null');
            $table->foreignId('tool_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type');
            $table->json('event_data')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['connection_id', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index(['duration_ms', 'created_at']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_analytics');
    }
};
