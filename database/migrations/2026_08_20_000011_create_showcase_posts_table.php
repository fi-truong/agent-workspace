<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('source_workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->string('department', 100);
            $table->string('title');
            $table->text('description');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('uses_count')->default(0);
            $table->string('badge', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_posts');
    }
};
