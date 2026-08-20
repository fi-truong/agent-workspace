<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_template_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_template_id')->constrained('agent_templates')->cascadeOnDelete();
            $table->string('feature_text');
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_template_features');
    }
};
