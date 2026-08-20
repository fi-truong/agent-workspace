<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('activity_title');
            $table->enum('source', ['agent_workspace', 'template_used']);
            $table->foreignId('related_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('related_agent_template_id')->nullable()->constrained('agent_templates')->nullOnDelete();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};
