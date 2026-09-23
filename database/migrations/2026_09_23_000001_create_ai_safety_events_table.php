<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_safety_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('classification', 30);
            $table->string('action', 20);
            $table->boolean('moderation_flagged')->default(false);
            $table->string('category', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['classification', 'created_at']);
            $table->index(['moderation_flagged', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_safety_events');
    }
};
