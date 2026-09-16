<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ChatCompletionService;
use App\Services\KnowledgeService;
use App\Services\PiiFilterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatMessageController extends Controller
{
    use AuthorizesRequests;

    public const CHAT_HISTORY_LIMIT = 20;
    public const MAX_MESSAGE_CHARS = 4000;

    /**
     * Bản KHÔNG streaming — giữ nguyên hành vi cũ, dùng cho nơi nào chưa chuyển sang stream().
     *
     * @return JsonResponse
     */
    public function store(
        Request $request,
        PiiFilterService $piiFilter,
        ChatCompletionService $chatService,
        KnowledgeService $knowledgeService,
    ) {
        set_time_limit(120);

        $blocked = $this->validateAndScanPii($request, $piiFilter);
        if ($blocked) {
            return $blocked;
        }

        $ctx = $this->prepareTurn($request, $knowledgeService);

        try {
            $completion = $chatService->complete($ctx['history'], $ctx['systemPrompt']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'blocked' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
            ], 502);
        }

        $this->persistAssistantReply($ctx['conversation'], $ctx['user'], $request, $completion);

        return response()->json([
            'blocked' => false,
            'conversation_id' => $ctx['conversation']->id,
            'reply' => $completion['content'],
        ]);
    }

    /**
     * Bản STREAMING — đẩy từng đoạn nội dung ra ngay khi OpenAI trả về, qua Server-Sent Events.
     * Frontend đọc bằng fetch() + ReadableStream (không dùng EventSource vì đây là POST).
     *
     * Các event gửi về:
     *   event: delta  data: {"text": "..."}      → nối thêm vào bong bóng chat đang gõ dở
     *   event: error  data: {"message": "..."}   → hiển thị lỗi, dừng streaming
     *   event: done   data: {"conversation_id":.., "reply": "..."} → hoàn tất, có thể render lại markdown/MathJax
     */
    public function stream(
        Request $request,
        PiiFilterService $piiFilter,
        ChatCompletionService $chatService,
        KnowledgeService $knowledgeService,
    ): StreamedResponse {
        set_time_limit(120);

        $blocked = $this->validateAndScanPii($request, $piiFilter);
        if ($blocked) {
            // Chặn PII: trả JSON thường (chưa mở stream), giữ hành vi giống store().
            return $blocked;
        }

        $ctx = $this->prepareTurn($request, $knowledgeService);

        return response()->stream(function () use ($ctx, $chatService, $request) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $completion = $chatService->streamComplete(
                    $ctx['history'],
                    $ctx['systemPrompt'],
                    function (string $delta) use ($send) {
                        $send('delta', ['text' => $delta]);
                    },
                );
            } catch (\RuntimeException $e) {
                $send('error', ['message' => $e->getMessage()]);

                return;
            }

            $this->persistAssistantReply($ctx['conversation'], $ctx['user'], $request, $completion);

            $send('done', [
                'conversation_id' => $ctx['conversation']->id,
                'reply' => $completion['content'],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // quan trọng nếu sau này chạy sau Nginx — chặn Nginx tự buffer
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Validate input + scan PII. Trả về JsonResponse nếu cần chặn ngay, null nếu ok để đi tiếp.
     */
    private function validateAndScanPii(Request $request, PiiFilterService $piiFilter): ?JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'images' => 'nullable|array',
            'images.*' => 'string',
        ]);

        $scan = $piiFilter->scan($request->message);

        if ($scan['flagged']) {
            return response()->json([
                'blocked' => true,
                'warning' => 'Tin nhắn của bạn có thể chứa thông tin cá nhân nhạy cảm ('
                    .implode(', ', array_keys($scan['matches']))
                    .'). Vui lòng chỉnh sửa và gửi lại — nội dung này CHƯA được lưu.',
            ], 422);
        }

        return null;
    }

    /**
     * Chuẩn bị conversation + history + system prompt — logic dùng chung cho store() và stream().
     *
     * @return array{conversation: Conversation, history: array, systemPrompt: ?string, user: User}
     */
    private function prepareTurn(Request $request, KnowledgeService $knowledgeService): array
    {
        $images = array_slice($request->input('images', []), 0, 4);

        Log::info('Chat send received', [
            'message' => $request->message,
            'agent_id' => $request->agent_id,
            'has_images' => count($images) > 0,
        ]);

        $user = $request->user() ?? User::where('email', 'ciec.coordinator.04@lsts.edu.vn')->first();

        /** @var Conversation $conversation */
        $conversation = $request->conversation_id
            ? Conversation::findOrFail((int) $request->conversation_id)
            : Conversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($request->message, 50),
            ]);

        if ($request->agent_id && $conversation->agent_id === null) {
            /** @var Agent|null $agent */
            $agent = Agent::find($request->agent_id);
            $canView = $agent && $this->canViewAgent($user, $agent);

            if ($canView) {
                $conversation->update(['agent_id' => $agent->id]);
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        $history = $conversation->messages()
            ->latest('id')
            ->limit(self::CHAT_HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Message $m): array {
                return [
                    'role' => $m->role,
                    'content' => Str::limit($m->content, self::MAX_MESSAGE_CHARS),
                ];
            })
            ->toArray();

        if (! empty($images)) {
            $history[count($history) - 1]['content'] = [
                ['type' => 'text', 'text' => $request->message],
                ...array_map(
                    fn (string $dataUrl) => ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    $images,
                ),
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($conversation, $knowledgeService);

        return [
            'conversation' => $conversation,
            'history' => $history,
            'systemPrompt' => $systemPrompt,
            'user' => $user,
        ];
    }

    private function persistAssistantReply(Conversation $conversation, User $user, Request $request, array $completion): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $completion['content'],
            'prompt_tokens' => $completion['prompt_tokens'],
            'completion_tokens' => $completion['completion_tokens'],
        ]);

        UsageLog::create([
            'user_id' => $user->id,
            'activity_title' => Str::limit($request->message, 100),
            'source' => 'agent_workspace',
            'related_conversation_id' => $conversation->id,
            'prompt_tokens' => $completion['prompt_tokens'],
            'completion_tokens' => $completion['completion_tokens'],
        ]);
    }

    private function buildSystemPrompt(Conversation $conversation, KnowledgeService $knowledgeService): ?string
    {
        $agent = $conversation->agent;

        if (! $agent) {
            return null;
        }

        $systemPrompt = $agent->system_prompt;

        if ($agent->knowledge_files) {
            $context = $knowledgeService->buildContext($agent->knowledge_files, $agent->user_id, $agent->id);

            if ($context !== '') {
                $systemPrompt = trim($systemPrompt ? $systemPrompt."\n\n".$context : 'Bạn là một trợ lý AI của trường LSTS.'."\n\n".$context);
            }
        }

        return $systemPrompt !== null && trim($systemPrompt) !== '' ? $systemPrompt : null;
    }

    private function canViewAgent(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id || $agent->is_shared;
    }
}
