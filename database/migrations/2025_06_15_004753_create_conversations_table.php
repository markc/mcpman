<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('mcp_connection_id')->constrained()->onDelete('cascade');
            $table->json('context')->nullable(); // Conversation context and metadata
            $table->json('settings')->nullable(); // Session-specific settings
            $table->string('status')->default('active'); // active, archived, ended
            $table->integer('message_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['mcp_connection_id', 'status']);
            $table->index('last_activity_at');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
