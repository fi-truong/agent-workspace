<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AgentWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Mock data cho guest (chưa login)
        $conversations = [];
        $myAgents = [];
        $workflows = [];
        $promptsUsedToday = 0;
        $promptsLimit = 50;
        $userName = 'Teacher / Staff';
        $userInitials = 'TS';

        // Tin nhắn initial nếu mở lại conversation cũ qua ?conversation_id=
        $initialMessages = [];
        $activeConversationId = $request->query('conversation_id');

        if ($user) {
            $conversations = $user->conversations()->latest()->get()->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => 'chat',
            ])->toArray();

            $myAgents = $user->agents()->latest()->get()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => 'agent',
            ])->toArray();

            $workflows = $user->workflows()->latest()->get()->map(fn ($w) => [
                'id' => $w->id, 'title' => $w->title, 'type' => 'workflow',
            ])->toArray();

            $promptsUsedToday = $user->usageLogs()->whereDate('created_at', today())->count();
            $promptsLimit = $user->daily_prompt_quota;
            $userName = $user->name;
            $userInitials = $user->initials;

            // Load history khi mở lại conversation cũ
            if ($activeConversationId) {
                $conversation = $user->conversations()->find($activeConversationId);

                if ($conversation) {
                    $initialMessages = $conversation->messages()
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
                        ->toArray();
                }
            }
        }

        $quickActions = [
            ['icon' => '💬', 'label' => 'Quick Chat', 'desc' => 'Ask anything, get help'],
            ['icon' => '🤖', 'label' => 'Create Agent', 'desc' => 'Build a custom AI assistant'],
        ];

        return view('ai-plus.agent-workspace.index', [
            'conversations' => $conversations,
            'myAgents' => $myAgents,
            'workflows' => $workflows,
            'quickActions' => $quickActions,
            'viewingAs' => 'Teacher / Staff',
            'userName' => $userName,
            'userInitials' => $userInitials,
            'promptsUsed' => $promptsUsedToday,
            'promptsLimit' => $promptsLimit,
            'initialMessages' => $initialMessages,
            'activeConversationId' => $activeConversationId,
        ]);
    }
}
