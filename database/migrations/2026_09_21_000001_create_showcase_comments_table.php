<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showcase_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->index(['showcase_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_comments');
    }
};
