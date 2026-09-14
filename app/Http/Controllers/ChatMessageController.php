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
use Illuminate\Support\Str;

class ChatMessageController extends Controller
{
    use AuthorizesRequests;

    /** Số tin nhắn gần nhất gom vào context khi gửi tiếp trong conversation. */
    public const CHAT_HISTORY_LIMIT = 20;

    /** Cap ký tự mỗi message trong history (tránh vượt context). */
    public const MAX_MESSAGE_CHARS = 4000;

    /**
     * @return JsonResponse
     */
    public function store(
        Request $request,
        PiiFilterService $piiFilter,
        ChatCompletionService $chatService,
        KnowledgeService $knowledgeService,
    ) {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'images' => 'nullable|array',
            'images.*' => 'string', // data URL base64 (vd data:image/png;base64,...)
        ]);

        // Tối đa 4 ảnh mỗi lượt gửi (tránh payload quá lớn + tốn token).
        $images = array_slice($request->input('images', []), 0, 4);

        $user = $request->user() ?? User::where('email', 'ciec.coordinator.04@lsts.edu.vn')->first();

        $scan = $piiFilter->scan($request->message);

        if ($scan['flagged']) {
            return response()->json([
                'blocked' => true,
                'warning' => 'Tin nhắn của bạn có thể chứa thông tin cá nhân nhạy cảm ('
                    .implode(', ', array_keys($scan['matches']))
                    .'). Vui lòng chỉnh sửa và gửi lại — nội dung này CHƯA được lưu.',
            ], 422);
        }

        /** @var Conversation $conversation */
        $conversation = $request->conversation_id
            ? Conversation::findOrFail((int) $request->conversation_id)
            : Conversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($request->message, 50),
            ]);

        // Gắn agent vào conversation (nếu được truyền agent_id và thuộc quyền).
        if ($request->agent_id && $conversation->agent_id === null) {
            /** @var Agent|null $agent */
            $agent = Agent::find($request->agent_id);

            if ($agent && $this->canViewAgent($user, $agent)) {
                $conversation->update(['agent_id' => $agent->id]);
            }
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        // Gom history (tối đa N tin) + build system prompt từ agent nếu có.
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

        // Nếu có ảnh kèm lượt gửi này → message user cuối trong lịch sử
        // biến thành multimodal (text + ảnh) để OpenAI hiểu.
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

        try {
            $completion = $chatService->complete($history, $systemPrompt);
        } catch (\RuntimeException $e) {
            // Lỗi đã được map sang message thân thiện; không để UI vỡ với 500.
            return response()->json([
                'blocked' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
            ], 502);
        }

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

        return response()->json([
            'blocked' => false,
            'conversation_id' => $conversation->id,
            'reply' => $completion['content'],
        ]);
    }

    /**
     * Build system prompt: nếu conversation gắn agent → dùng system_prompt của agent
     * + nối knowledge text (nếu có). Ngược lại → null (service dùng default).
     */
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
