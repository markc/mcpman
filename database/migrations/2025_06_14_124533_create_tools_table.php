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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('category')->default('general');
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->json('tags')->nullable();
            $table->decimal('average_execution_time', 8, 2)->nullable();
            $table->decimal('success_rate', 5, 2)->default(100.00);
            $table->unsignedBigInteger('mcp_connection_id');
            $table->unsignedBigInteger('discovered_by_user_id');
            $table->timestamps();

            $table->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('cascade');
            $table->foreign('discovered_by_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['is_active', 'category']);
            $table->index(['mcp_connection_id', 'is_active']);
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
