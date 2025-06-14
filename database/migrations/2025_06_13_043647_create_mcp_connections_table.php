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
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint_url');
            $table->string('transport_type')->default('stdio'); // stdio, http, websocket
            $table->json('auth_config')->nullable();
            $table->json('capabilities')->nullable();
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
    }
};
