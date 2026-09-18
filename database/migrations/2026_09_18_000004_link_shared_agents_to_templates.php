<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->foreignId('source_agent_id')->nullable()->unique()->constrained('agents')->cascadeOnDelete();
        });

        DB::table('agents')
            ->where('is_shared', true)
            ->orderBy('id')
            ->each(function (object $agent): void {
                DB::table('agent_templates')->insert([
                    'source_agent_id' => $agent->id,
                    'icon' => '🤖',
                    'name' => $agent->title,
                    'description' => $agent->description ?: 'A team-shared AI agent.',
                    'preview_class' => 'shared-agent',
                    'badge' => 'new',
                    'uses_count' => 0,
                    'category' => 'shared',
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->dropForeign(['source_agent_id']);
            $table->dropUnique(['source_agent_id']);
            $table->dropColumn('source_agent_id');
        });
    }
};
