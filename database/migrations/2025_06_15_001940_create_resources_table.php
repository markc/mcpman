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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // file, directory, api_endpoint, database, etc.
            $table->string('path_or_uri');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_cached')->default(false);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_indexable')->default(true);
            $table->json('permissions')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('parent_resource_id')->nullable();
            $table->unsignedBigInteger('mcp_connection_id');
            $table->unsignedBigInteger('discovered_by_user_id');
            $table->timestamps();

            $table->foreign('parent_resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('mcp_connection_id')->references('id')->on('mcp_connections')->onDelete('cascade');
            $table->foreign('discovered_by_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['mcp_connection_id', 'type']);
            $table->index(['is_cached', 'expires_at']);
            $table->index('last_accessed_at');
            $table->index(['parent_resource_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
