<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ai_artifacts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->string('mime_type'); $table->string('path'); $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('email_drafts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('recipients')->nullable(); $table->string('subject'); $table->longText('body'); $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('email_drafts'); Schema::dropIfExists('ai_artifacts'); }
};
