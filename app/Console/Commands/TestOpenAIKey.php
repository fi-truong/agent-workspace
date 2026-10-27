<?php

namespace App\Console\Commands;

use App\Services\ChatCompletionService;
use App\Services\ChatModelService;
use App\Services\OpenAIClient;
use Illuminate\Console\Command;

class TestOpenAIKey extends Command
{
    protected $signature = 'openai:test-key
        {--live : Run billed chat, streaming and function-calling probes with synthetic input}
        {--model= : Exact chat model to test with --live (gpt-6-luna or gpt-6-sol)}';

    protected $description = 'Verify API access; GET /models is free, --live explicitly runs small billed model probes';

    public function handle(OpenAIClient $client): int
    {
        if ($this->option('model') !== null && ! $this->option('live')) {
            $this->error('Use --live with --model to test inference. Live probes consume API tokens.');

            return self::FAILURE;
        }
        if (! config('openai.api_key')) {
            $this->error('OPENAI_API_KEY chưa được đặt trong .env. Thêm key rồi chạy lại.');

            return self::FAILURE;
        }

        if ($this->option('live')) {
            return $this->probeModel(app(ChatCompletionService::class));
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

    private function probeModel(ChatCompletionService $chat): int
    {
        $model = $this->option('model') ?? ChatModelService::defaultModel();
        if (! isset(ChatModelService::options()[$model])) {
            $this->error('Choose an enabled model: '.implode(', ', array_keys(ChatModelService::options())).'.');

            return self::FAILURE;
        }

        $this->warn('Live API probes consume tokens. Only synthetic markers are sent; no user data or database rows are read or written.');
        $marker = 'AI_PLUS_LIVE_OK';
        $messages = [['role' => 'user', 'content' => 'Return exactly '.$marker.' and nothing else.']];
        $system = 'You are running a synthetic API integration check. Follow the requested marker exactly.';
        $inputTokens = 0;
        $outputTokens = 0;
        $probe = 'chat';
        try {
            foreach (['chat', 'streaming', 'function calling'] as $probe) {
                $deltas = '';
                if ($probe === 'function calling') {
                    $tools = [[
                        'type' => 'function',
                        'function' => [
                            'name' => 'ai_plus_probe', 'description' => 'Return the synthetic integration marker. No side effects.', 'strict' => true,
                            'parameters' => [
                                'type' => 'object',
                                'properties' => ['marker' => ['type' => 'string', 'enum' => [$marker]]],
                                'required' => ['marker'], 'additionalProperties' => false,
                            ],
                        ],
                    ]];
                    $result = $chat->complete([['role' => 'user', 'content' => 'Call ai_plus_probe with marker '.$marker.'. Do not answer in plain text.']], $system, tools: $tools, maxCompletionTokens: 256, timeoutSeconds: 40, model: $model);
                    $call = $result['tool_calls'][0] ?? null;
                    $passed = $call !== null && $call['name'] === 'ai_plus_probe'
                        && (json_decode($call['arguments'], true)['marker'] ?? null) === $marker;
                } elseif ($probe === 'streaming') {
                    $result = $chat->streamComplete($messages, $system, function (string $delta) use (&$deltas): void {
                        $deltas .= $delta;
                    }, maxCompletionTokens: 256, timeoutSeconds: 40, model: $model);
                    $passed = trim($deltas) === $marker && trim($result['content']) === $marker;
                } else {
                    $result = $chat->complete($messages, $system, maxCompletionTokens: 256, timeoutSeconds: 40, model: $model);
                    $passed = trim($result['content']) === $marker;
                }

                $inputTokens += $result['prompt_tokens'];
                $outputTokens += $result['completion_tokens'];
                $actualModel = $result['model'] ?? '';
                $passed = $passed && ($actualModel === $model || str_starts_with($actualModel, $model.'-'))
                    && $result['prompt_tokens'] > 0 && $result['completion_tokens'] > 0;
                if (! $passed) {
                    $this->error('FAIL '.$probe.': unexpected marker, model or usage metadata.');

                    return self::FAILURE;
                }
                $this->info('PASS '.$probe.' | API model: '.$actualModel.' | input: '.$result['prompt_tokens'].' | output: '.$result['completion_tokens']);
            }
        } catch (\Throwable $exception) {
            // Do not print provider exception bodies, keys or project identifiers.
            $this->error('FAIL '.$probe.': API request failed ('.$exception::class.').');

            return self::FAILURE;
        }

        $units = ($inputTokens + $outputTokens) * ChatModelService::multiplier($model);
        $this->line('Total API tokens: '.($inputTokens + $outputTokens).'; equivalent AI+ Credits: '.ChatModelService::formatCredits($units).' (diagnostic only, not deducted from any user).');

        return self::SUCCESS;
    }
}
