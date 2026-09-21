<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\TokenQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AgentWorkspaceController extends Controller
{
    public function index(Request $request, TokenQuotaService $tokenQuotaService): View
    {
        $user = Auth::user();

        // Mock data cho guest (chưa login)
        $conversations = [];
        $myAgents = [];
        $workflows = [];
        $tokenQuota = null;
        $userName = 'Teacher / Staff';
        $userInitials = 'TS';

        // Tin nhắn initial nếu mở lại conversation cũ qua ?conversation_id=
        $initialMessages = [];
        $activeAgent = null;
        $activeConversationTitle = null;
        $activeConversationId = $request->query('conversation_id');
        $selectedAgentId = null;

        if ($user) {
            $conversations = $user->conversations()->where('type', \App\Models\Conversation::TYPE_CHAT)->latest('updated_at')->get()->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => 'chat',
            ])->toArray();

            $myAgents = $user->agents()->latest()->get()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => 'agent',
            ])->toArray();

            $workflows = $user->workflows()->latest()->get()->map(fn ($w) => [
                'id' => $w->id, 'title' => $w->title, 'type' => 'workflow',
            ])->toArray();

            $tokenQuota = $tokenQuotaService->summary($user);
            $recentArtifacts = $user->aiArtifacts()->latest()->limit(10)->get(['id', 'name', 'mime_type', 'created_at']);
            $recentEmailDrafts = $user->emailDrafts()->latest()->limit(10)->get(['id', 'subject', 'created_at']);
            $userName = $user->name;
            $userInitials = $user->initials;

            if ($request->filled('agent_id')) {
                $selectedAgentId = $user->agents()->whereKey($request->integer('agent_id'))->value('id');
            }

            // Load history khi mở lại conversation cũ
            if ($activeConversationId) {
                $conversation = $user->conversations()->where('type', \App\Models\Conversation::TYPE_CHAT)->find($activeConversationId);

                if ($conversation) {
                    $initialMessages = $conversation->messages()
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($m) => ['role' => $m->role, 'content' => $this->secureAttachmentUrls($m->content)])
                        ->toArray();

                    // Agent + tên prompt (conversation) gắn → hiển thị trong chat.
                    $activeAgent = $conversation->agent;
                    $activeConversationTitle = $conversation->title;
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
            'activeAgent' => $activeAgent,
            'activeConversationTitle' => $activeConversationTitle,
            'tokenQuota' => $tokenQuota,
            'initialMessages' => $initialMessages,
            'activeConversationId' => $activeConversationId,
            'selectedAgentId' => $selectedAgentId,
            'recentArtifacts' => $recentArtifacts ?? collect(),
            'recentEmailDrafts' => $recentEmailDrafts ?? collect(),
            'imageGenerationEnabled' => AppSetting::boolean('ai_plus_image_generation_enabled'),
        ]);
    }

    private function secureAttachmentUrls(string $content): string
    {
        return preg_replace_callback(
            '#/storage/chat-attachments/(\d+)/([A-Za-z0-9_.-]+)#',
            fn (array $matches): string => route('ai-plus.agent-workspace.attachments.show', [
                'conversation' => $matches[1],
                'filename' => $matches[2],
            ], false),
            $content,
        ) ?? $content;
    }
}
