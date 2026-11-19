<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AgentTemplate;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\ShowcaseUse;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentConnectionService;
use App\Services\AgentSharingService;
use App\Services\KnowledgeService;
use App\Services\SharedAgentTemplateSyncService;
use App\Support\AgentCategories;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly KnowledgeService $knowledgeService,
        private readonly AgentAvatarService $agentAvatarService,
        private readonly SharedAgentTemplateSyncService $sharedAgentTemplateSyncService,
        private readonly AgentConnectionService $agentConnections,
        private readonly AgentSharingService $sharing,
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        $user = Auth::user();

        if (! $user) {
            $agents = collect();
            $usedSharedAgents = collect();
        } else {
            $agents = $user->agents()->with('sharedRecipients:users.id')->latest()->get();
            $usedSharedAgents = ShowcaseUse::query()
                ->where('user_id', $user->id)
                ->with('showcase.sourceAgent.sharedRecipients:users.id')
                ->latest('updated_at')
                ->get()
                ->map(function (ShowcaseUse $use): ?Agent {
                    $agent = $use->showcase?->sourceAgent;
                    if ($agent) {
                        $agent->setAttribute('showcase_post_id', $use->showcase_post_id);
                    }

                    return $agent;
                })
                ->filter(fn (?Agent $agent) => $agent !== null && $this->sharing->canUse($user, $agent))
                ->reject(fn (Agent $agent) => $agents->contains('id', $agent->id))
                ->values();
        }

        if ($request->wantsJson()) {
            return response()->json($agents->concat($usedSharedAgents->map(fn (Agent $agent): array => $agent->only(['id', 'title', 'description', 'avatar_url', 'is_shared', 'sharing_access'])))->values());
        }

        $availableAgentIds = $agents->pluck('id')->merge($usedSharedAgents->pluck('id'))->unique();
        $agentConversations = $user
            ? $user->conversations()
                ->where('type', Conversation::TYPE_CHAT)
                ->whereIn('agent_id', $availableAgentIds)
                ->latest('updated_at')
                ->get(['id', 'agent_id', 'title'])
                ->groupBy('agent_id')
                ->map(fn ($conversations) => $conversations->map(fn (Conversation $conversation) => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ])->values()->all())
                ->all()
            : [];

        return view('ai-plus.agent-workspace.agents.index', [
            'agents' => $agents,
            'usedSharedAgents' => $usedSharedAgents,
            'agentConversations' => $agentConversations,
            'imageGenerationEnabled' => AppSetting::boolean('ai_plus_image_generation_enabled'),
        ]);
    }

    public function store(StoreAgentRequest $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Please login.'], 401);
            }

            return redirect()->route('login.local.form');
        }

        $sharingSettings = $this->sharing->settings($request);
        $this->agentConnections->validate($request->input('connections', []), $user);
        $this->knowledgeService->ensureAgentKnowledgeLimits($request->file('knowledge', []));

        /** @var Agent $agent */
        $agent = $user->agents()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'category_group' => $request->validated('category_group'),
            'category' => $request->validated('category'),
            'system_prompt' => $request->input('system_prompt'),
            ...$sharingSettings['attributes'],
        ]);

        $this->sharing->save($agent, $sharingSettings);
        $this->saveKnowledgeFiles($request->file('knowledge', []), $user->id, $agent);
        if ($request->hasFile('avatar')) {
            $this->agentAvatarService->replace($agent, $request->file('avatar'));
        }
        $this->knowledgeService->indexAgent($agent);
        $this->agentConnections->sync($agent, $request->input('connections', []));
        $agent->refresh();
        $this->sharedAgentTemplateSyncService->sync($agent);

        if ($templateId = $request->integer('template_id')) {
            AgentTemplate::query()->where('status', 'published')->whereNull('source_agent_id')
                ->find($templateId)?->recordUse($user);
        }

        if ($request->wantsJson()) {
            return response()->json($agent, 201);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent created successfully.');
    }

    public function show(Agent $agent): JsonResponse|View
    {
        $this->authorize('view', $agent);

        if (request()->wantsJson()) {
            $agent->load('sharedRecipients:users.id,users.name,users.email,users.department,users.is_active');

            return response()->json([...$agent->attributesToArray(), 'sharing_scopes' => $agent->sharingAudiences(), 'sharing_users' => $agent->sharedRecipients->map(fn (User $user): array => $user->only(['id', 'name', 'email', 'department', 'is_active']))->all(), 'connections' => $this->agentConnections->setup($agent)]);
        }

        return view('ai-plus.agent-workspace.agents.show', ['agent' => $agent]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $agent);
        $sharingSettings = $this->sharing->settings($request, $agent);
        $category = $request->validated('category');
        $group = $request->validated('category_group');
        $newSchoolShare = $sharingSettings['attributes']['is_shared']
            && $sharingSettings['attributes']['sharing_scope'] === 'school'
            && (! $agent->is_shared || $agent->sharing_scope !== 'school');

        if ($newSchoolShare && ! AgentCategories::contains($group ?? $agent->category_group, $category ?? $agent->category)) {
            throw ValidationException::withMessages(['category_group' => 'Choose a Group and Category before sharing this Agent with everyone at school.']);
        }

        if ($request->has('connections') || $request->boolean('connections_configured')) {
            $this->agentConnections->validate($request->input('connections', []), $request->user(), $agent);
        }

        // Validate and persist Knowledge first. This prevents partial Agent edits when
        // the aggregate Knowledge limit is exceeded.
        $this->syncKnowledgeFiles($request, $agent);

        $agent->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'system_prompt' => $request->input('system_prompt'),
            ...($category === null ? [] : ['category_group' => $group, 'category' => $category]),
        ]);

        $this->sharing->save($agent, $sharingSettings);
        if ($request->has('connections') || $request->boolean('connections_configured')) {
            $this->agentConnections->sync($agent, $request->input('connections', []));
        }

        if ($request->hasFile('avatar')) {
            $this->agentAvatarService->replace($agent, $request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            $this->agentAvatarService->delete($agent);
        }

        $this->knowledgeService->indexAgent($agent);
        $agent->refresh();
        $this->sharedAgentTemplateSyncService->sync($agent);

        if ($request->wantsJson()) {
            return response()->json($agent);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent updated successfully.');
    }

    public function destroy(Request $request, Agent $agent): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $agent);

        $this->knowledgeService->deleteAgentKnowledge($agent->user_id, $agent->id);
        $this->agentAvatarService->delete($agent);

        // The owner can remove their own chats with this agent. Conversations
        // created by other people through Use-only sharing remain theirs; detach
        // the deleted agent while preserving their message history.
        $agent->conversations()
            ->where('user_id', '!=', $agent->user_id)
            ->update(['agent_id' => null]);
        $agent->conversations()
            ->where('user_id', $agent->user_id)
            ->delete();
        $agent->showcasePosts()->delete();

        $agent->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Agent deleted']);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent deleted successfully.');
    }

    public function avatar(Request $request, Agent $agent): StreamedResponse
    {
        $canView = $this->sharing->canView($request->user(), $agent);
        abort_unless($canView && $agent->avatar_path, 404);

        $disk = Storage::disk('agent-avatars');
        abort_unless($disk->exists($agent->avatar_path), 404);

        return $disk->response($agent->avatar_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function sharingUsers(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = mb_strtolower(trim($data['q']));
        if (mb_strlen($term) < 2) {
            return response()->json(['users' => []]);
        }
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $users = User::query()->where('is_active', true)->where('id', '!=', $request->user()->id)
            ->where(function ($query) use ($escaped): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            })
            ->orderByRaw("CASE WHEN LOWER(email) = ? OR LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? ESCAPE '!' OR LOWER(email) LIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$term, $term, $escaped.'%', $escaped.'%'])
            ->orderBy('name')->orderBy('id')->limit(10)
            ->get(['id', 'name', 'email', 'department']);

        return response()->json(['users' => $users])->header('Cache-Control', 'private, no-store');
    }

    public function connectionOptions(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => $this->agentConnections->enabled(),
            'agents' => $this->agentConnections->options($request->user()),
        ]);
    }

    /**
     * Lưu file knowledge mới + ghi JSON vào cột knowledge.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function saveKnowledgeFiles(array $files, int $userId, Agent $agent): void
    {
        if ($files === []) {
            return;
        }

        $saved = $this->knowledgeService->saveFiles(array_values($files), $userId, $agent->id);

        if ($saved !== []) {
            $agent->update(['knowledge' => json_encode($saved)]);
        }
    }

    /**
     * Đồng bộ knowledge khi update: giữ file cũ (trừ file bị xóa) + thêm file mới.
     */
    private function syncKnowledgeFiles(UpdateAgentRequest $request, Agent $agent): void
    {
        /** @var array<int, array{path: string, original_name: string}> $existing */
        $existing = $agent->knowledge_files;
        $newFiles = $request->file('knowledge', []);
        $removePaths = $request->input('knowledge_remove', []) ?? [];

        // Khi không gửi file mới và không có yêu cầu xóa → giữ nguyên.
        if ($newFiles === [] && $removePaths === []) {
            return;
        }

        // Lọc bỏ file bị đánh dấu xóa (theo path).
        $kept = array_values(array_filter(
            $existing,
            fn (array $file): bool => ! in_array($file['path'], $removePaths, true),
        ));

        $this->knowledgeService->ensureAgentKnowledgeLimits(array_values($newFiles), $kept);

        // Lưu file mới nếu có.
        $savedNew = $this->knowledgeService->saveFiles(array_values($newFiles), $agent->user_id, $agent->id);

        $merged = array_merge($kept, $savedNew);

        // Xóa khỏi disk các file bị remove.
        foreach ($existing as $file) {
            if (in_array($file['path'], $removePaths, true)) {
                try {
                    $disk = Storage::disk('knowledge');
                    if ($disk->exists($file['path'])) {
                        $disk->delete($file['path']);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete removed knowledge file', [
                        'path' => $file['path'],
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        $agent->update(['knowledge' => json_encode($merged)]);
    }
}
