<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_templates', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 10)->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('preview_class', 50)->nullable();
            $table->enum('badge', ['none', 'popular', 'new'])->default('none');
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_templates');
    }
};
