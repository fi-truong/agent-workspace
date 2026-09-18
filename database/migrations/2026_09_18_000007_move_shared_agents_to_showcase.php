<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agent_templates')->whereNotNull('source_agent_id')->delete();

        DB::table('agents')->where('is_shared', true)->orderBy('id')->each(function (object $agent): void {
            $department = DB::table('users')->where('id', $agent->user_id)->value('department') ?: 'General';
            DB::table('showcase_posts')->updateOrInsert(
                ['source_agent_id' => $agent->id],
                [
                    'author_id' => $agent->user_id,
                    'department' => $department,
                    'title' => $agent->title,
                    'description' => $agent->description ?: 'A school-shared AI agent.',
                    'status' => 'published',
                    'badge' => 'New',
                    'published_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });
    }

    public function down(): void
    {
        DB::table('showcase_posts')->whereNotNull('source_agent_id')->delete();
    }
};
