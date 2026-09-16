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
use Illuminate\Support\Facades\Storage;
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

        $this->persistAssistantReply($ctx['conversation'], $ctx['user'], $request, $completion, $ctx['createdNew']);

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
    ): StreamedResponse|JsonResponse {
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

            $this->persistAssistantReply($ctx['conversation'], $ctx['user'], $request, $completion, $ctx['createdNew']);

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
     * @return array{conversation: Conversation, history: array<int, array{role: string, content: mixed}>, systemPrompt: ?string, user: User, createdNew: bool}
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

        // Title mặc định: thời gian + vài từ đầu prompt (AI tóm tắt sẽ ghi đè sau lần trả lời đầu).
        $createdNew = $request->conversation_id ? false : true;

        /** @var Conversation $conversation */
        $conversation = $request->conversation_id
            ? Conversation::findOrFail((int) $request->conversation_id)
            : Conversation::create([
                'user_id' => $user->id,
                'title' => now()->format('Y.m.d H:i').' · '.Str::limit($request->message, 40),
            ]);

        if ($request->agent_id && $conversation->agent_id === null) {
            /** @var Agent|null $agent */
            $agent = Agent::find($request->agent_id);
            $canView = $agent && $this->canViewAgent($user, $agent);

            if ($canView) {
                $conversation->update(['agent_id' => $agent->id]);
            }
        }

        // Lưu ảnh kèm thành file (để hiện lại trong lịch sử chat) + nối path vào content.
        $attachedPaths = $this->persistChatImages($images, $conversation->id);

        $userContent = $request->message;
        if ($attachedPaths !== []) {
            // Markdown: mỗi ảnh hiển thị trong bubble user khi mở lại.
            foreach ($attachedPaths as $p) {
                $userContent .= "\n\n![Ảnh đính kèm]({$p})";
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userContent,
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

        $systemPrompt = $this->buildSystemPrompt($conversation, $knowledgeService, $request->message);

        return [
            'conversation' => $conversation,
            'history' => $history,
            'systemPrompt' => $systemPrompt,
            'user' => $user,
            'createdNew' => $createdNew,
        ];
    }

    /**
     * @param  array{content: string, prompt_tokens: int, completion_tokens: int}  $completion
     */
    private function persistAssistantReply(Conversation $conversation, User $user, Request $request, array $completion, bool $createdNew = false): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $completion['content'],
            'prompt_tokens' => $completion['prompt_tokens'],
            'completion_tokens' => $completion['completion_tokens'],
        ]);

        // Conversation mới → tóm tắt title bằng AI (1 lần duy nhất, fallback giữ title hiện tại nếu lỗi).
        if ($createdNew) {
            $this->summarizeConversationTitle($conversation);
        }

        UsageLog::create([
            'user_id' => $user->id,
            'activity_title' => Str::limit($request->message, 100),
            'source' => 'agent_workspace',
            'related_conversation_id' => $conversation->id,
            'prompt_tokens' => $completion['prompt_tokens'],
            'completion_tokens' => $completion['completion_tokens'],
        ]);
    }

    private function buildSystemPrompt(Conversation $conversation, KnowledgeService $knowledgeService, string $query): ?string
    {
        $agent = $conversation->agent;

        if (! $agent) {
            return null;
        }

        $systemPrompt = $agent->system_prompt;

        if ($agent->knowledge_files) {
            // RAG: lấy đoạn liên quan nhất đến câu hỏi; nếu rỗng (chưa index/embed lỗi) → fallback đọc nguyên file.
            $context = $knowledgeService->retrieveContext($agent, $query);

            if ($context === '') {
                $context = $knowledgeService->buildContext($agent->knowledge_files, $agent->user_id, $agent->id);
            }

            if ($context !== '') {
                $systemPrompt = trim($systemPrompt ? $systemPrompt."\n\n".$context : 'Bạn là một trợ lý AI của trường LSTS.'."\n\n".$context);
            }
        }

        return $systemPrompt !== null && trim($systemPrompt) !== '' ? $systemPrompt : null;
    }

    /**
     * Lưu các ảnh data URL (kèm khi gửi chat) thành file, trả danh sách URL public.
     *
     * @param  array<int, string>  $images  data URL base64
     * @return array<int, string> các URL /storage/chat-attachments/...
     */
    private function persistChatImages(array $images, int $conversationId): array
    {
        $saved = [];

        foreach ($images as $i => $dataUrl) {
            if (! str_starts_with($dataUrl, 'data:image/')) {
                continue;
            }

            // Tách mime + base64
            if (! preg_match('#^data:image/(\w+);base64,(.+)$#s', $dataUrl, $m)) {
                continue;
            }

            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $base64 = base64_decode((string) $m[2]);

            if ($base64 === '') {
                continue;
            }

            $filename = $conversationId.'/'.time().'_'.$i.'.'.$ext;
            Storage::disk('chat-attachments')->put($filename, $base64);

            $url = Storage::disk('chat-attachments')->url($filename);
            $saved[] = $url;
        }

        return $saved;
    }

    /**
     * Tóm tắt cuộc hội thoại (tin đầu tiên) thành title ngắn gọn bằng AI.
     * Chạy 1 lần khi tạo conversation mới; nếu lỗi → giữ title hiện tại (thời gian + vài từ đầu).
     */
    /**
     * Đổi tên (title) của conversation.
     */
    public function rename(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:120']);

        $conversation->update(['title' => trim($request->title)]);

        return response()->json(['ok' => true, 'title' => $conversation->title]);
    }

    /**
     * Xóa một conversation (prompt) — messages cascade theo FK.
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    private function summarizeConversationTitle(Conversation $conversation): void
    {
        try {
            $firstMessage = $conversation->messages()->orderBy('id')->first();
            if (! $firstMessage) {
                return;
            }

            $summary = app(ChatCompletionService::class)->complete(
                [
                    [
                        'role' => 'user',
                        'content' => 'Tạo tiêu đề ngắn gọn (tối đa 60 ký tự, tiếng Việt, không dấu chấm câu) '
                            .'cho đoạn yêu cầu sau. Chỉ trả về tiêu đề, không gì khác: "'.$firstMessage->content.'"',
                    ],
                ],
                null,
            );

            $title = trim($summary['content']);

            if ($title !== '' && mb_strlen($title) <= 80) {
                $conversation->update([
                    'title' => now()->format('Y.m.d H:i').' · '.$title,
                ]);
            }
        } catch (\Throwable $e) {
            // Giữ title mặc định (thời gian + vài từ đầu) nếu tóm tắt lỗi — không làm hỏng request.
            Log::info('Summarize conversation title skipped', ['conversation_id' => $conversation->id]);
        }
    }

    private function canViewAgent(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id || $agent->is_shared;
    }
}
