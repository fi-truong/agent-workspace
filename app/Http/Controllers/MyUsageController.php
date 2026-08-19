<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MyUsageController extends Controller
{
    public function index()
    {
        $stats = [
            'prompts' => 156,
            'tokens' => '12.4K',
            'timeSaved' => '2.3h',
            'agentsCreated' => 8,
            'agentsShared' => 3,
        ];

        $activities = [
            [
                'icon' => '💬',
                'title' => 'Email draft for parent meeting',
                'source' => 'Agent Workspace',
                'time' => '10:32 AM',
                'tokens' => '847 tok',
                'isTemplate' => false,
            ],
            [
                'icon' => '📋',
                'title' => 'Math Problem Generator',
                'source' => 'Template used',
                'time' => '09:45 AM',
                'tokens' => '1,234 tok',
                'isTemplate' => true,
            ],
            [
                'icon' => '💬',
                'title' => 'Lesson plan - Grade 7 Math',
                'source' => 'Agent Workspace',
                'time' => 'Yesterday',
                'tokens' => '2,156 tok',
                'isTemplate' => false,
            ],
            [
                'icon' => '💬',
                'title' => 'Summary of student progress',
                'source' => 'Agent Workspace',
                'time' => 'Yesterday',
                'tokens' => '1,089 tok',
                'isTemplate' => false,
            ],
            [
                'icon' => '📋',
                'title' => 'Rubric Builder',
                'source' => 'Template used',
                'time' => '2 days ago',
                'tokens' => '1,567 tok',
                'isTemplate' => true,
            ],
        ];

        $topTasks = [
            ['icon' => '📧', 'name' => 'Email drafting', 'count' => 34],
            ['icon' => '📚', 'name' => 'Lesson planning', 'count' => 28],
            ['icon' => '📝', 'name' => 'Assessment creation', 'count' => 19],
            ['icon' => '📊', 'name' => 'Data analysis', 'count' => 15],
            ['icon' => '💡', 'name' => 'Brainstorming', 'count' => 12],
        ];

        return view('ai-plus.my-usage.index', [
            'stats' => $stats,
            'activities' => $activities,
            'topTasks' => $topTasks,
            'viewingAs' => 'Teacher / Staff',
            'userName' => 'Fi Truong',
            'userInitials' => 'FT',
            'userRole' => 'CIEC Coordinator • Teacher',
            'promptsUsed' => 24,
            'promptsLimit' => 50,
        ]);
    }
}
