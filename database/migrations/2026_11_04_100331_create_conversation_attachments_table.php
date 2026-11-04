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
        if (! Schema::hasTable('conversation_attachments')) {
            Schema::create('conversation_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('message_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('original_name', 255);
                $table->string('extension', 20)->nullable();
                $table->string('source_type', 20);
                $table->string('status', 24);
                $table->longText('extracted_text')->nullable();
                $table->string('disk', 40)->nullable();
                $table->text('path')->nullable();
                $table->unsignedInteger('character_count')->default(0);
                $table->boolean('extraction_complete')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // Keep this step recoverable if CREATE TABLE succeeded but a later
        // ALTER TABLE failed on a database with a 64-character index limit.
        if (! Schema::hasIndex('conversation_attachments', 'conv_att_conv_user_msg_idx')) {
            Schema::table('conversation_attachments', function (Blueprint $table): void {
                $table->index(['conversation_id', 'user_id', 'message_id'], 'conv_att_conv_user_msg_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_attachments');
    }
};
