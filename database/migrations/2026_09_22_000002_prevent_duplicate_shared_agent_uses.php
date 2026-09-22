<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('copied_from_agent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('agents')
                ->nullOnDelete();
            $table->unique(['user_id', 'copied_from_agent_id']);
        });

        Schema::create('showcase_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showcase_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['showcase_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_uses');

        Schema::table('agents', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'copied_from_agent_id']);
            $table->dropForeign(['copied_from_agent_id']);
            $table->dropColumn('copied_from_agent_id');
        });
    }
};
