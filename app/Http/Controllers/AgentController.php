<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\ShowcaseUse;
use App\Services\AgentAvatarService;
use App\Services\KnowledgeService;
use App\Services\SharedAgentTemplateSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly KnowledgeService $knowledgeService,
        private readonly AgentAvatarService $agentAvatarService,
        private readonly SharedAgentTemplateSyncService $sharedAgentTemplateSyncService,
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        $user = Auth::user();

        if (! $user) {
            $agents = collect();
            $usedSharedAgents = collect();
        } else {
            $agents = $user->agents()->latest()->get();
            $usedSharedAgents = ShowcaseUse::query()
                ->where('user_id', $user->id)
                ->with('showcase.sourceAgent')
                ->latest('updated_at')
                ->get()
                ->map(fn (ShowcaseUse $use) => $use->showcase?->sourceAgent)
                ->filter(fn (?Agent $agent) => $agent !== null && $agent->is_shared && $agent->sharing_access !== 'copy')
                ->reject(fn (Agent $agent) => $agents->contains('id', $agent->id))
                ->values();
        }

        if ($request->wantsJson()) {
            return response()->json($agents->concat($usedSharedAgents)->values());
        }

        return view('ai-plus.agent-workspace.agents.index', [
            'agents' => $agents,
            'usedSharedAgents' => $usedSharedAgents,
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

        $this->knowledgeService->ensureAgentKnowledgeLimits($request->file('knowledge', []));

        /** @var Agent $agent */
        $agent = $user->agents()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'system_prompt' => $request->input('system_prompt'),
            'is_shared' => $request->boolean('is_shared'),
            'sharing_access' => $request->boolean('is_shared') ? $request->input('sharing_access') : 'use_only',
            'shared_with_team_id' => null,
        ]);

        $this->saveKnowledgeFiles($request->file('knowledge', []), $user->id, $agent);
        if ($request->hasFile('avatar')) {
            $this->agentAvatarService->replace($agent, $request->file('avatar'));
        }
        $this->knowledgeService->indexAgent($agent);
        $agent->refresh();
        $this->sharedAgentTemplateSyncService->sync($agent);

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
            return response()->json($agent);
        }

        return view('ai-plus.agent-workspace.agents.show', ['agent' => $agent]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $agent);

        // Validate and persist Knowledge first. This prevents partial Agent edits when
        // the aggregate Knowledge limit is exceeded.
        $this->syncKnowledgeFiles($request, $agent);

        $agent->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'system_prompt' => $request->input('system_prompt'),
            'is_shared' => $request->boolean('is_shared'),
            'sharing_access' => $request->boolean('is_shared') ? $request->input('sharing_access') : 'use_only',
            'shared_with_team_id' => null,
        ]);

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

    public function avatar(Request $request, Agent $agent)
    {
        $canView = $request->user()?->id === $agent->user_id || $agent->is_shared;
        abort_unless($canView && $agent->avatar_path, 404);

        $disk = Storage::disk('agent-avatars');
        abort_unless($disk->exists($agent->avatar_path), 404);

        return $disk->response($agent->avatar_path, null, [
            'Cache-Control' => 'private, max-age=86400',
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
