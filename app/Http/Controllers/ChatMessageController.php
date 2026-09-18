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
use App\Services\TokenQuotaService;
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

    public const MAX_CURRENT_TURN_CHARS = 40000;

    public const MAX_DOCUMENT_CONTEXT_CHARS = 6000;

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
        TokenQuotaService $tokenQuotaService,
    ) {
        set_time_limit(120);

        $blocked = $this->validateAndScanPii($request, $piiFilter);
        if ($blocked) {
            return $blocked;
        }
        if ($tokenQuotaService->isExhausted($request->user())) {
            return $this->tokenQuotaExceededResponse();
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
            'title' => $ctx['conversation']->fresh()->title,
            'reply' => $completion['content'],
            'token_quota' => $tokenQuotaService->summary($ctx['user']),
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
        TokenQuotaService $tokenQuotaService,
    ): StreamedResponse|JsonResponse {
        set_time_limit(120);

        $blocked = $this->validateAndScanPii($request, $piiFilter);
        if ($blocked) {
            // Chặn PII: trả JSON thường (chưa mở stream), giữ hành vi giống store().
            return $blocked;
        }
        if ($tokenQuotaService->isExhausted($request->user())) {
            return $this->tokenQuotaExceededResponse();
        }

        $ctx = $this->prepareTurn($request, $knowledgeService);

        return response()->stream(function () use ($ctx, $chatService, $request, $tokenQuotaService) {
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

                $this->persistAssistantReply($ctx['conversation'], $ctx['user'], $request, $completion, $ctx['createdNew']);

                $send('done', [
                    'conversation_id' => $ctx['conversation']->id,
                    'title' => $ctx['conversation']->fresh()->title,
                    'reply' => $completion['content'],
                    'token_quota' => $tokenQuotaService->summary($ctx['user']),
                ]);
            } catch (\Throwable $e) {
                Log::error('Streaming chat response failed', [
                    'conversation_id' => $ctx['conversation']->id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                $send('error', [
                    'message' => $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Không thể hoàn tất phản hồi. Vui lòng thử lại.',
                ]);
            }
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
            'message' => 'nullable|string|max:5000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'documents' => 'nullable|array|max:5',
            'documents.*.name' => 'required_with:documents|string|max:255',
            'documents.*.data_url' => 'required_with:documents|string',
        ]);

        // Chuan hoa: tu day $request->message luon la string (khong null) - cho phep gui
        // chi anh/tai lieu dinh kem ma khong can go chu.
        $request->merge(['message' => (string) $request->input('message', '')]);

        if (
            trim($request->message) === ''
            && empty($request->input('images', []))
            && empty($request->input('documents', []))
        ) {
            return response()->json([
                'blocked' => false,
                'error' => 'Vui lòng nhập nội dung hoặc đính kèm ít nhất 1 tệp.',
            ], 422);
        }

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
        $documents = array_slice($request->input('documents', []), 0, 5);

        Log::info('Chat send received', [
            'message' => $request->message,
            'agent_id' => $request->agent_id,
            'has_images' => count($images) > 0,
            'has_documents' => count($documents) > 0,
        ]);

        /** @var User $user */
        $user = $request->user();

        // Title mặc định: thời gian + vài từ đầu prompt (AI tóm tắt sẽ ghi đè sau lần trả lời đầu).
        $createdNew = $request->conversation_id ? false : true;

        /** @var Conversation $conversation */
        $conversation = $request->conversation_id
            ? Conversation::whereKey((int) $request->conversation_id)
                ->where('user_id', $user->id)
                ->firstOrFail()
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

        // Tai lieu (khong phai anh) dinh kem trong chat: trich text, KHONG luu file goc lai
        // (khac Knowledge cua Agent) - chi dua noi dung trich duoc vao ngu canh cua luot chat nay.
        $documentBlocks = $this->extractChatDocuments($documents, $knowledgeService, $request->message);

        $userContent = $request->message;
        if ($attachedPaths !== []) {
            // Markdown: mỗi ảnh hiển thị trong bubble user khi mở lại.
            foreach ($attachedPaths as $p) {
                $userContent .= "\n\n![Ảnh đính kèm]({$p})";
            }
        }
        foreach ($documentBlocks as $block) {
            $userContent .= "\n\n".$block;
        }

        $userMessage = Message::create([
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
            ->map(function (Message $m) use ($userMessage): array {
                return [
                    'role' => $m->role,
                    'content' => Str::limit(
                        $m->content,
                        $m->id === $userMessage->id ? self::MAX_CURRENT_TURN_CHARS : self::MAX_MESSAGE_CHARS,
                    ),
                ];
            })
            ->toArray();

        if (! empty($images)) {
            $multimodalText = trim($userContent)
                ."\n\n[Đính kèm ".count($images).' hình ảnh. Hãy phân tích các hình này cùng toàn bộ tài liệu đính kèm.]';

            $history[count($history) - 1]['content'] = [
                ['type' => 'text', 'text' => Str::limit($multimodalText, self::MAX_CURRENT_TURN_CHARS)],
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
            if (! preg_match('#^data:image/(png|jpeg|gif|webp);base64,([A-Za-z0-9+/=]+)$#s', $dataUrl, $m)) {
                continue;
            }

            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $base64 = base64_decode((string) $m[2], true);

            if ($base64 === false || $base64 === '') {
                continue;
            }

            $filename = Str::uuid().'.'.$ext;
            $path = $conversationId.'/'.$filename;
            Storage::disk('chat-attachments')->put($path, $base64);

            $saved[] = route('ai-plus.agent-workspace.attachments.show', [
                'conversation' => $conversationId,
                'filename' => $filename,
            ], false);
        }

        return $saved;
    }

    public function attachment(Request $request, Conversation $conversation, string $filename)
    {
        $this->ensureOwnsConversation($request, $conversation);
        abort_unless(preg_match('/^[A-Za-z0-9_.-]+$/', $filename), 404);

        $path = $conversation->id.'/'.$filename;
        abort_unless(Storage::disk('chat-attachments')->exists($path), 404);

        return Storage::disk('chat-attachments')->response($path);
    }

    /**
     * Trich van ban tu cac tai lieu (khong phai anh) dinh kem trong o chat - gui kem message
     * duoi dang data URL base64 (giong co che anh), nhung KHONG luu file goc lai, chi lay text
     * de dua vao ngu canh cua luot chat hien tai. Co PII filter (Layer 1) + gioi han do dai.
     *
     * @param  array<int, array{name?: string, data_url?: string}>  $documents
     * @return array<int, string> moi phan tu la 1 khoi markdown san de noi vao noi dung tin nhan
     */
    private function extractChatDocuments(array $documents, KnowledgeService $knowledgeService, string $query): array
    {
        $blocks = [];
        $maxBytes = KnowledgeService::MAX_FILE_SIZE_KB * 1024;

        foreach ($documents as $doc) {
            $name = is_array($doc) ? (string) ($doc['name'] ?? 'tệp đính kèm') : 'tệp đính kèm';
            $dataUrl = is_array($doc) ? (string) ($doc['data_url'] ?? '') : '';

            if (! preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUrl, $m)) {
                continue;
            }

            $binary = base64_decode((string) $m[2]);

            if ($binary === false || $binary === '') {
                continue;
            }

            if (strlen($binary) > $maxBytes) {
                $blocks[] = "📎 Tài liệu đính kèm: {$name}\n(Tệp vượt quá giới hạn ".KnowledgeService::MAX_FILE_SIZE_KB.' KB — chưa đọc được nội dung.)';

                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, KnowledgeService::CHAT_DOCUMENT_EXTENSIONS, true)) {
                $blocks[] = "📎 Tài liệu đính kèm: {$name}\n(Định dạng .{$extension} chưa được hỗ trợ đọc nội dung.)";

                continue;
            }

            $text = $knowledgeService->extractTextFromBinary($binary, $extension);
            $text = $text !== '' ? $knowledgeService->filterAndTruncate($text, self::MAX_DOCUMENT_CONTEXT_CHARS) : '';
            $context = $text !== '' ? $knowledgeService->retrieveInlineContext($text, $query, $name) : '';

            $blocks[] = $context !== ''
                ? "📎 Tài liệu đính kèm: {$name}\n{$context}"
                : "📎 Tài liệu đính kèm: {$name}\n(Không trích được nội dung văn bản từ file này.)";
        }

        return $blocks;
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
        $this->ensureOwnsConversation($request, $conversation);
        $request->validate(['title' => 'required|string|max:120']);

        $conversation->update(['title' => trim($request->title)]);

        return response()->json(['ok' => true, 'title' => $conversation->title]);
    }

    /**
     * Xóa một conversation (prompt) — messages cascade theo FK.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureOwnsConversation($request, $conversation);
        // Keep token accounting intact, but remove the deleted chat from My Usage activity.
        $conversation->usageLogs()->update(['hidden_at' => now()]);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    private function ensureOwnsConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()?->id, 403);
    }

    private function tokenQuotaExceededResponse(): JsonResponse
    {
        return response()->json([
            'blocked' => false,
            'error' => 'Bạn đã dùng hết quota token cho giai đoạn hiện tại. Vui lòng liên hệ quản trị viên.',
            'retryable' => false,
        ], 429);
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
        return $user->can('view', $agent);
    }
}
