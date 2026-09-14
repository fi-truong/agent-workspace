<?php

namespace App\Console\Commands;

use App\Services\OpenAIClient;
use Illuminate\Console\Command;

class TestOpenAIKey extends Command
{
    protected $signature = 'openai:test-key';

    protected $description = 'Kiểm tra OPENAI_API_KEY có hợp lệ hay không (chạy GET /models, không tốn token)';

    public function handle(OpenAIClient $client): int
    {
        if (! config('openai.api_key')) {
            $this->error('OPENAI_API_KEY chưa được đặt trong .env. Thêm key rồi chạy lại.');

            return self::FAILURE;
        }

        $this->info('Đang kiểm tra key...');

        $result = $client->verifyKey();

        if (($result['ok'] ?? false) === true) {
            $models = $result['models'] ?? [];
            $this->info('✓ Key hợp lệ. Số model truy cập được: '.($result['count'] ?? 0).'.');

            if ($models) {
                $this->line('  Models: '.implode(', ', $models));
            }

            return self::SUCCESS;
        }

        $this->error('✗ '.($result['error'] ?? 'Lỗi kiểm tra key.'));

        return self::FAILURE;
    }
}
