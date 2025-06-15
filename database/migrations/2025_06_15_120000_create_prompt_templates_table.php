<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->longText('template_content');
            $table->json('variables')->nullable(); // Required variables
            $table->text('instructions')->nullable(); // Usage instructions
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
            $table->index(['is_public', 'is_active']);
            $table->index(['usage_count', 'average_rating']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
