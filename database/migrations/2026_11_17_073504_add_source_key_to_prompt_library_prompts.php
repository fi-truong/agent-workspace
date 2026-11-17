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
        Schema::table('prompt_library_prompts', function (Blueprint $table) {
            $table->string('source_key', 120)->nullable()->unique('prompt_library_source_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prompt_library_prompts', function (Blueprint $table) {
            $table->dropUnique('prompt_library_source_key_unique');
            $table->dropColumn('source_key');
        });
    }
};
