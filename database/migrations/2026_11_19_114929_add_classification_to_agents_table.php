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
        Schema::table('agents', function (Blueprint $table) {
            $table->string('category_group', 40)->nullable();
            $table->string('category', 60)->nullable();
            $table->index(['category_group', 'category'], 'agents_classification_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex('agents_classification_idx');
            $table->dropColumn(['category_group', 'category']);
        });
    }
};
