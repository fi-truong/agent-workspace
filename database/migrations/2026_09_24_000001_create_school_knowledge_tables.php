<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->string('source_url', 2048)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('status', 20)->default('ready');
            $table->text('failure_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('school_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('embedding');
            $table->timestamps();

            $table->index('school_knowledge_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_knowledge_chunks');
        Schema::dropIfExists('school_knowledge_sources');
    }
};
