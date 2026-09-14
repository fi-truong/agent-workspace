<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Xử lý lỗi khi gọi OpenAI, trả về thông điệp thân thiện cho UI.
 * Tránh để exception lộ cấu trúc internal ra cho end-user.
 */
class OpenAIErrorMapper
{
    public static function message(\Throwable $e): string
    {
        Log::error('OpenAI call failed', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        $message = (string) $e->getMessage();

        $known = [
            'insufficient_quota' => 'Tài khoản chưa có đủ credit/token để gọi API. Kiểm tra phần Billing trên OpenAI.',
            '401' => 'API key không hợp lệ hoặc đã bị thu hồi. Kiểm tra lại OPENAI_API_KEY trong .env.',
            '403' => 'API key không có quyền truy cập endpoint này (403).',
            '404' => 'Model hoặc endpoint không tồn tại (404). Kiểm tra OPENAI_MODEL.',
            '429' => 'Bạn đã vượt quota hoặc đang bị giới hạn tốc độ (429). Kiểm tra billing/credit và hạn mức.',
        ];

        foreach ($known as $needle => $fallback) {
            if (str_contains($message, $needle)) {
                return $fallback;
            }
        }

        if ($e instanceof ConnectionException || str_contains($message, 'timeout') || str_contains($message, 'cURL')) {
            return 'Không thể kết nối tới OpenAI. Kiểm tra mạng/domain (server có firewall?) và thử lại.';
        }

        return 'Đã có lỗi khi liên hệ với OpenAI. Vui lòng thử lại sau.';
    }
}
