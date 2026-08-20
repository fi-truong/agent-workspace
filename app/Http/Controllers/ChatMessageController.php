<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ChatCompletionService;
use App\Services\PiiFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatMessageController extends Controller
{
    public function store(Request $request, PiiFilterService $piiFilter, ChatCompletionService $chatService)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        $user = User::where('email', 'fi.truong@lsts.edu.vn')->first();

        $scan = $piiFilter->scan($request->message);

        if ($scan['flagged']) {
            return response()->json([
                'blocked' => true,
                'warning' => 'Tin nhắn của bạn có thể chứa thông tin cá nhân nhạy cảm ('
                    .implode(', ', array_keys($scan['matches']))
                    .'). Vui lòng chỉnh sửa và gửi lại — nội dung này CHƯA được lưu.',
            ], 422);
        }

        $conversation = $request->conversation_id
            ? Conversation::findOrFail($request->conversation_id)
            : Conversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($request->message, 50),
            ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        $completion = $chatService->complete($request->message);

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
}
