<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_template_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');
            $table->json('variables_used')->nullable();
            $table->longText('rendered_content');
            $table->decimal('rating', 2, 1)->nullable(); // 1-5 rating
            $table->text('feedback')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['prompt_template_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['success', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_template_usages');
    }
};
