<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Services\KnowledgeService;
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

    public function __construct(private readonly KnowledgeService $knowledgeService) {}

    public function index(Request $request): JsonResponse|View
    {
        $user = Auth::user();

        if (! $user) {
            $agents = collect();
        } else {
            $agents = $user->agents()->latest()->get();
        }

        if ($request->wantsJson()) {
            return response()->json($agents);
        }

        return view('ai-plus.agent-workspace.agents.index', [
            'agents' => $agents,
        ]);
    }

    public function store(StoreAgentRequest $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        /** @var Agent $agent */
        $agent = $user->agents()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'system_prompt' => $request->input('system_prompt'),
            'is_shared' => $request->boolean('is_shared'),
        ]);

        $this->saveKnowledgeFiles($request->file('knowledge', []), $user->id, $agent);

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

        $agent->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'system_prompt' => $request->input('system_prompt'),
            'is_shared' => $request->boolean('is_shared'),
        ]);

        $this->syncKnowledgeFiles($request, $agent);

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

        $agent->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Agent deleted']);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent deleted successfully.');
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
        $removePaths = $request->input('knowledge_remove', []);

        // Khi không gửi file mới và không có yêu cầu xóa → giữ nguyên.
        if ($newFiles === [] && $removePaths === []) {
            return;
        }

        // Lọc bỏ file bị đánh dấu xóa (theo path).
        $kept = array_values(array_filter(
            $existing,
            fn (array $file): bool => ! in_array($file['path'], $removePaths, true),
        ));

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
