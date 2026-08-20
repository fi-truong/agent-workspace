<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_tags', function (Blueprint $table) {
            $table->foreignId('showcase_post_id')->constrained('showcase_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['showcase_post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_tags');
    }
};
