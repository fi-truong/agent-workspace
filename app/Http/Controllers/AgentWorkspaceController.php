<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AgentWorkspaceController extends Controller
{
    public function index()
    {
        $conversations = [
            [
                'id' => 1,
                'title' => 'Email draft for parent meeting',
                'type' => 'chat',
                'date' => 'today',
            ],
            [
                'id' => 2,
                'title' => 'Lesson plan - Grade 7 Math',
                'type' => 'chat',
                'date' => 'today',
            ],
            [
                'id' => 3,
                'title' => 'Summary of student progress',
                'type' => 'chat',
                'date' => 'yesterday',
            ],
        ];

        $myAgents = [
            [
                'id' => 1,
                'title' => 'Math Quiz Generator',
                'type' => 'agent',
            ],
            [
                'id' => 2,
                'title' => 'Email Assistant (Bilingual)',
                'type' => 'agent',
            ],
        ];

        $workflows = [
            [
                'id' => 1,
                'title' => 'Weekly Report Generator',
                'type' => 'workflow',
            ],
            [
                'id' => 2,
                'title' => 'Student Progress Summary',
                'type' => 'workflow',
            ],
        ];

        $quickActions = [
            ['icon' => '💬', 'label' => 'Quick Chat', 'desc' => 'Ask anything, get help'],
            ['icon' => '🤖', 'label' => 'Create Agent', 'desc' => 'Build a custom AI assistant'],
            ['icon' => '⚡', 'label' => 'New Workflow', 'desc' => 'Automate repetitive tasks'],
            ['icon' => '📄', 'label' => 'Analyze Document', 'desc' => 'Upload and analyze files'],
            ['icon' => '📊', 'label' => 'Generate Report', 'desc' => 'Create data summaries'],
            ['icon' => '📧', 'label' => 'Draft Email', 'desc' => 'Bilingual EN/VI emails'],
        ];

        return view('ai-plus.agent-workspace.index', [
            'conversations' => $conversations,
            'myAgents' => $myAgents,
            'workflows' => $workflows,
            'quickActions' => $quickActions,
            'viewingAs' => 'Teacher / Staff',
            'userName' => 'Fi Truong',
            'userInitials' => 'FT',
            'promptsUsed' => 24,
            'promptsLimit' => 50,
        ]);
    }
}
