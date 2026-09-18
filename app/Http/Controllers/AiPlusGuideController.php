<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\UsageLog;
use App\Services\ChatCompletionService;
use App\Services\TokenQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPlusGuideController extends Controller
{
    public function reply(
        Request $request,
        ChatCompletionService $chatCompletion,
        TokenQuotaService $tokenQuota,
    ): JsonResponse {
        abort_unless(AppSetting::boolean('ai_plus_homepage_guide_enabled'), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:800'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
        ]);

        $user = $request->user();

        if ($tokenQuota->isExhausted($user)) {
            return response()->json([
                'error' => 'Bạn đã dùng hết hạn mức token của tháng này. Vui lòng quay lại vào chu kỳ tiếp theo.',
                'token_quota' => $tokenQuota->summary($user),
            ], 429);
        }

        $history = collect($data['history'] ?? [])
            ->map(fn (array $item): array => [
                'role' => $item['role'],
                'content' => $item['content'],
            ])
            ->push(['role' => 'user', 'content' => $data['message']])
            ->all();

        try {
            $completion = $chatCompletion->complete($history, $this->systemPrompt());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'retryable' => true,
            ], 502);
        }

        UsageLog::create([
            'user_id' => $user->id,
            'activity_title' => 'AI Plus Guide',
            'source' => 'ai_plus_guide',
            'prompt_tokens' => $completion['prompt_tokens'],
            'completion_tokens' => $completion['completion_tokens'],
        ]);

        return response()->json([
            'reply' => $completion['content'],
            'token_quota' => $tokenQuota->summary($user),
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là AI Plus Guide của LSTS. Nhiệm vụ duy nhất là hướng dẫn ngắn gọn để người dùng chọn đúng khu vực trong AI Plus và biết bước tiếp theo.

Bạn có thể hướng dẫn về:
- Agent Workspace: trò chuyện với AI, làm việc với file, tạo agent; đường dẫn /ai-plus/agent-workspace.
- Prompt Library: tìm và dùng prompt mẫu; đường dẫn /ai-plus/prompt-library.
- Agent Templates: dùng hoặc tùy biến agent mẫu; đường dẫn /ai-plus/agent-templates.
- Sharing & Showcase: xem các sản phẩm được chia sẻ; đường dẫn /ai-plus/sharing-showcase.
- My Usage: xem mức dùng token; đường dẫn /ai-plus/my-usage.
- AI Policy & Guidelines: quy định dùng AI an toàn; đường dẫn /ai-plus/ai-policy.
- Support: gửi yêu cầu hỗ trợ; đường dẫn /ai-plus/support.

Trả lời bằng tiếng Việt, tối đa 4 câu hoặc 4 gạch đầu dòng. Nếu phù hợp, nêu rõ tên khu vực và đường dẫn. Không thực hiện tác vụ, không yêu cầu hoặc xử lý file, không tự nhận đã truy cập dữ liệu người dùng. Nếu câu hỏi nằm ngoài AI Plus, lịch sự nói bạn chỉ hỗ trợ định hướng sử dụng AI Plus.
PROMPT;
    }
}
