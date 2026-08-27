<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UsageLog;
use Illuminate\Database\Seeder;

class UsageLogSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'ciec.coordinator.04@lsts.edu.vn')->first();
        if (! $user) {
            return;
        }

        // 5 hoạt động gần đây (hiển thị chi tiết ở "Recent Activity")
        $recent = [
            ['title' => 'Email draft for parent meeting', 'source' => 'agent_workspace', 'category' => 'Email drafting', 'prompt_tokens' => 500, 'completion_tokens' => 347, 'created_at' => now()->subHours(2)],
            ['title' => 'Math Problem Generator', 'source' => 'template_used', 'category' => 'Assessment creation', 'prompt_tokens' => 700, 'completion_tokens' => 534, 'created_at' => now()->subHours(3)],
            ['title' => 'Lesson plan - Grade 7 Math', 'source' => 'agent_workspace', 'category' => 'Lesson planning', 'prompt_tokens' => 1200, 'completion_tokens' => 956, 'created_at' => now()->subDay()],
            ['title' => 'Summary of student progress', 'source' => 'agent_workspace', 'category' => 'Data analysis', 'prompt_tokens' => 600, 'completion_tokens' => 489, 'created_at' => now()->subDay()],
            ['title' => 'Rubric Builder', 'source' => 'template_used', 'category' => 'Assessment creation', 'prompt_tokens' => 900, 'completion_tokens' => 667, 'created_at' => now()->subDays(2)],
        ];

        foreach ($recent as $log) {
            UsageLog::create([
                'user_id' => $user->id,
                'activity_title' => $log['title'],
                'source' => $log['source'],
                'task_category' => $log['category'],
                'prompt_tokens' => $log['prompt_tokens'],
                'completion_tokens' => $log['completion_tokens'],
                'created_at' => $log['created_at'],
            ]);
        }

        // Thêm 1 lượng nhỏ log nền để "Top Tasks" có số liệu tổng hợp thực tế
        // (không cố khớp đúng con số cũ trong mock — số liệu thật sẽ tự tích lũy theo thời gian dùng)
        $categories = ['Email drafting', 'Lesson planning', 'Assessment creation', 'Data analysis', 'Brainstorming'];
        foreach ($categories as $category) {
            $count = random_int(3, 10);
            for ($i = 0; $i < $count; $i++) {
                UsageLog::create([
                    'user_id' => $user->id,
                    'activity_title' => $category.' task',
                    'source' => 'agent_workspace',
                    'task_category' => $category,
                    'prompt_tokens' => random_int(300, 1000),
                    'completion_tokens' => random_int(200, 800),
                    'created_at' => now()->subDays(random_int(0, 14)),
                ]);
            }
        }
    }
}
