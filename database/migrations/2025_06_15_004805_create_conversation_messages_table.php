<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->string('role'); // user, assistant, system, tool_call, tool_result, error
            $table->longText('content');
            $table->json('metadata')->nullable(); // Tool calls, timestamps, etc.
            $table->json('context')->nullable(); // Message-specific context
            $table->string('tool_name')->nullable(); // For tool-related messages
            $table->json('tool_arguments')->nullable(); // Tool call arguments
            $table->json('tool_result')->nullable(); // Tool execution result
            $table->integer('sequence_number'); // Order within conversation
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->index(['conversation_id', 'sequence_number']);
            $table->index(['conversation_id', 'role']);
            $table->index(['conversation_id', 'sent_at']);
            $table->index('tool_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
