<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentWorkspaceController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $conversations = $user->conversations()->latest()->get()->map(fn ($c) => [
            'id' => $c->id, 'title' => $c->title, 'type' => 'chat',
        ])->toArray();

        $myAgents = $user->agents()->latest()->get()->map(fn ($a) => [
            'id' => $a->id, 'title' => $a->title, 'type' => 'agent',
        ])->toArray();

        $workflows = $user->workflows()->latest()->get()->map(fn ($w) => [
            'id' => $w->id, 'title' => $w->title, 'type' => 'workflow',
        ])->toArray();

        $quickActions = [
            ['icon' => '💬', 'label' => 'Quick Chat', 'desc' => 'Ask anything, get help'],
            ['icon' => '🤖', 'label' => 'Create Agent', 'desc' => 'Build a custom AI assistant'],
            ['icon' => '⚡', 'label' => 'New Workflow', 'desc' => 'Automate repetitive tasks'],
            ['icon' => '📄', 'label' => 'Analyze Document', 'desc' => 'Upload and analyze files'],
            ['icon' => '📊', 'label' => 'Generate Report', 'desc' => 'Create data summaries'],
            ['icon' => '📧', 'label' => 'Draft Email', 'desc' => 'Bilingual EN/VI emails'],
        ];

        $promptsUsedToday = $user->usageLogs()->whereDate('created_at', today())->count();

        return view('ai-plus.agent-workspace.index', [
            'conversations' => $conversations,
            'myAgents' => $myAgents,
            'workflows' => $workflows,
            'quickActions' => $quickActions,
            'viewingAs' => 'Teacher / Staff',
            'userName' => $user->name,
            'userInitials' => $user->initials,
            'promptsUsed' => $promptsUsedToday,
            'promptsLimit' => $user->daily_prompt_quota,
        ]);
    }
}
