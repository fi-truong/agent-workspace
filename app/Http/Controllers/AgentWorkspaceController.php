<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Agent;
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
        $selectedAgentName = null;
        $agentAccessMessage = null;

        if ($user) {
            $conversationModels = $user->conversations()
                ->where('type', \App\Models\Conversation::TYPE_CHAT)
                ->with('agent:id,title,user_id')
                ->latest('updated_at')
                ->get();
            $conversations = $conversationModels->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => 'chat', 'agent_id' => $c->agent_id,
            ])->toArray();
            $quickConversations = $conversationModels->whereNull('agent_id')->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title,
            ])->values()->all();
            $agentConversations = $conversationModels->filter(fn ($c) => $c->agent_id !== null && $c->agent !== null)
                ->groupBy('agent_id')
                ->map(fn ($group) => $group->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values()->all())
                ->all();

            $myAgents = $user->agents()->latest()->get()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => 'agent', 'is_owned' => true,
            ])->keyBy('id');
            // A Use-only shared Agent is not owned by this user, but its
            // conversations still belong in the same sidebar tree.
            foreach ($conversationModels->filter(fn ($c) => $c->agent !== null) as $conversation) {
                if (! $myAgents->has($conversation->agent_id)) {
                    $myAgents->put($conversation->agent_id, [
                        'id' => $conversation->agent->id,
                        'title' => $conversation->agent->title,
                        'type' => 'agent',
                        'is_owned' => false,
                    ]);
                }
            }
            $myAgents = $myAgents->values()->all();

            $workflows = $user->workflows()->latest()->get()->map(fn ($w) => [
                'id' => $w->id, 'title' => $w->title, 'type' => 'workflow',
            ])->toArray();

            $tokenQuota = $tokenQuotaService->summary($user);
            $recentArtifacts = $user->aiArtifacts()->latest()->limit(10)->get(['id', 'name', 'mime_type', 'created_at']);
            $recentEmailDrafts = $user->emailDrafts()->latest()->limit(10)->get(['id', 'subject', 'created_at']);
            $userName = $user->name;
            $userInitials = $user->initials;

            if ($request->filled('agent_id')) {
                $requestedAgent = Agent::find($request->integer('agent_id'), ['id', 'user_id', 'title', 'is_shared', 'sharing_access']);
                if ($requestedAgent?->user_id === $user->id
                    || ($requestedAgent?->is_shared && $requestedAgent->sharing_access !== 'copy')) {
                    $selectedAgentId = $requestedAgent->id;
                    $selectedAgentName = $requestedAgent->title;
                } elseif ($requestedAgent?->is_shared) {
                    $agentAccessMessage = 'You do not own this agent. Find it in Sharing & Showcase and use Copy and edit to add it to your workspace.';
                } else {
                    $agentAccessMessage = 'This agent is no longer available.';
                }
            }

            // Load history khi mở lại conversation cũ
            if ($activeConversationId) {
                $conversation = $user->conversations()->where('type', \App\Models\Conversation::TYPE_CHAT)->find($activeConversationId);

                if ($conversation) {
                    $initialMessages = $conversation->messages()
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($m) => ['role' => $m->role, 'content' => $this->displayMessageContent($m->role, $m->content)])
                        ->toArray();

                    // Files created before download links were stored in the
                    // assistant reply still exist in private artifact storage.
                    // Surface their links again when this conversation opens.
                    $history = implode("\n", array_column($initialMessages, 'content'));
                    $missingArtifactLinks = $user->aiArtifacts()
                        ->where('conversation_id', $conversation->id)
                        ->oldest()
                        ->get(['id', 'name'])
                        ->filter(fn ($artifact) => ! str_contains($history, '/ai-plus/artifacts/'.$artifact->id.'/download'))
                        ->map(fn ($artifact) => '📄 **File ready:** ['.$artifact->name.']('.route('ai-plus.artifacts.download', $artifact, false).')')
                        ->implode("\n");

                    if ($missingArtifactLinks !== '') {
                        $initialMessages[] = [
                            'role' => 'assistant',
                            'content' => "**Files created in this conversation**\n\n".$missingArtifactLinks,
                        ];
                    }

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
            'quickConversations' => $quickConversations ?? [],
            'agentConversations' => $agentConversations ?? [],
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
            'selectedAgentName' => $selectedAgentName,
            'agentAccessMessage' => $agentAccessMessage,
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

    /** Correct historic model refusals when a real artifact was created anyway. */
    private function displayMessageContent(string $role, string $content): string
    {
        $content = $this->secureAttachmentUrls($content);
        if ($role !== 'assistant' || ! str_contains($content, '/ai-plus/artifacts/')) {
            return $content;
        }

        $normalized = mb_strtolower($content);
        $incorrectRefusal = str_contains($normalized, 'không có công cụ')
            || str_contains($normalized, 'không thể tạo')
            || str_contains($normalized, 'không thể cung cấp')
            || str_contains($normalized, 'cannot create')
            || str_contains($normalized, 'do not have the tools');
        if (! $incorrectRefusal) {
            return $content;
        }

        preg_match_all('/📄 \*\*File ready:\*\* \[[^\]]+\]\([^\)]+\)/u', $content, $matches);

        return $matches[0] === []
            ? $content
            : 'Đã tạo file từ nội dung liên quan trong cuộc chat này.'."\n\n".implode("\n", $matches[0]);
    }
}
