<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatCompletionService;
use App\Services\ChatResponseContinuationService;
use App\Services\Guardrail\RegexPiiFilter;
use App\Services\OpenAIClient;
use App\Services\TokenQuotaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses()->group('openai', 'unit');

it('continues a truncated streamed reply once and emits only the continuation delta', function () {
    config(['openai.api_key' => 'sk-test', 'openai.max_tokens' => 8192]);

    $streamEvents = [
        ['choices' => [['delta' => ['content' => " □ | □ | □ |\n| Nhiệt độ món ăn | □ | □ | □ |"], 'finish_reason' => null]], 'model' => 'gpt-6-luna'],
        ['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 9000, 'completion_tokens' => 90], 'model' => 'gpt-6-luna'],
    ];
    $streamBody = collect($streamEvents)
        ->map(fn (array $event): string => 'data: '.json_encode($event)."\n\n")
        ->push("data: [DONE]\n\n")
        ->implode('');
    Http::fake(['https://api.openai.com/*' => Http::response($streamBody, 200, ['Content-Type' => 'text/event-stream'])]);
    Http::preventStrayRequests();

    $quota = Mockery::mock(TokenQuotaService::class);
    $quota->shouldReceive('remainingTokens')->once()->andReturn(100_000);
    $chat = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);
    $service = new ChatResponseContinuationService($chat, $quota);
    $conversation = new Conversation;
    $conversation->id = 42;
    $user = new User;
    $partial = "| Câu hỏi | Rất không hài lòng | Bình thường | Rất hài lòng |\n|---|---|---|---|\n| Hương vị món ăn | □ | □ | □ |\n| Độ tươi của nguyên liệu |";
    $deltas = [];

    $result = $service->continueIfTruncated([
        'content' => $partial,
        'prompt_tokens' => 120,
        'completion_tokens' => 8192,
        'model' => 'gpt-6-luna',
        'finish_reason' => 'length',
        'tool_calls' => [],
    ], $conversation, $user, 'gpt-6-luna', [
        ['role' => 'user', 'content' => 'Tạo khảo sát song ngữ về chất lượng bữa ăn.'],
    ], 'You help create school surveys.', 'safe-id', static function (string $delta) use (&$deltas): void {
        $deltas[] = $delta;
    });

    expect($result['content'])->toBe($partial." □ | □ | □ |\n| Nhiệt độ món ăn | □ | □ | □ |")
        ->and($result['finish_reason'])->toBe('stop')
        ->and($result['prompt_tokens'])->toBe(9120)
        ->and($result['completion_tokens'])->toBe(8282)
        ->and($deltas)->toBe([" □ | □ | □ |\n| Nhiệt độ món ăn | □ | □ | □ |"]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => ! isset($request->data()['tools'])
        && $request->data()['max_completion_tokens'] === 8192
        && str_contains(json_encode($request->data()['messages'], JSON_UNESCAPED_UNICODE), 'Continue the answer from the exact point'));
});
