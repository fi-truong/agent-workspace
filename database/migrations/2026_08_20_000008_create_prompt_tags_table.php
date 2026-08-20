<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_tags', function (Blueprint $table) {
            $table->foreignId('prompt_id')->constrained('prompt_library_prompts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['prompt_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_tags');
    }
};
