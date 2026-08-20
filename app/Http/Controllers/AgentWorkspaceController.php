<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AgentWorkspaceController extends Controller
{
    public function index()
    {
        // TẠM THỜI: chưa có SSO thật nên lấy cố định user demo "Fi Truong".
        // Khi tích hợp Entra ID xong, thay dòng dưới bằng: $user = auth()->user();
        $user = User::where('email', 'fi.truong@lsts.edu.vn')->first();

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

        // promptsUsed tính thật từ usage_logs hôm nay, thay vì số cứng
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
