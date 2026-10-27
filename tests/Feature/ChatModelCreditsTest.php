<?php

use App\Models\Conversation;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ChatModelService;
use App\Services\TokenQuotaService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    config(['openai.api_key' => 'sk-test', 'openai.model' => 'gpt-6-luna', 'openai.sol_enabled' => true, 'openai.rag_embeddings_enabled' => false]);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/*' => fn (Request $request) => Http::response([
        'choices' => [['message' => ['content' => 'A concise school response.']]],
        'usage' => ['prompt_tokens' => 700, 'completion_tokens' => 300],
        'model' => $request['model'],
    ])]);
});

it('retains old usage and custom balances while adding precise shared credits', function () {
    $this->user->update(['token_quota_limit' => 20_000_000]);
    DB::table('usage_logs')->insert([
        'user_id' => $this->user->id, 'activity_title' => 'Legacy', 'source' => 'agent_workspace',
        'prompt_tokens' => 1200, 'completion_tokens' => 300, 'quota_units' => null, 'created_at' => now(),
    ]);
    foreach ([['gpt-6-luna', 1], ['gpt-6-sol', 1000], ['gpt-image-2.5-flare', 1000]] as [$model, $tokens]) {
        UsageLog::create(['user_id' => $this->user->id, 'activity_title' => 'New usage', 'source' => 'agent_workspace', 'model' => $model, 'prompt_tokens' => $tokens]);
    }
    expect(app(TokenQuotaService::class)->summary($this->user))->toMatchArray([
        'used' => 22501, 'limit' => 20_000_000, 'remaining' => 19_977_499,
        'credits_used' => 22.501, 'credits_limit' => 20000.0, 'credits_remaining' => 19977.499,
    ]);
    expect(UsageLog::where('model', 'gpt-6-sol')->value('quota_units'))->toBe(20000)
        ->and(UsageLog::where('activity_title', 'Legacy')->value('quota_units'))->toBeNull()
        ->and(ChatModelService::formatCredits(20000000))->toBe('20,000')
        ->and(ChatModelService::formatCredits(1))->toBe('0.001');
});

it('does not reprice recorded Sol usage when Sol is disabled and counts hidden usage', function () {
    $log = UsageLog::create(['user_id' => $this->user->id, 'activity_title' => 'Sol', 'source' => 'agent_workspace', 'model' => 'gpt-6-sol', 'prompt_tokens' => 1]);
    $log->update(['hidden_at' => now()]);
    config(['openai.sol_enabled' => false]);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(20)
        ->and(ChatModelService::options())->not->toHaveKey('gpt-6-sol');
});

it('sends the selected Sol model and remembers it without changing the global Luna default', function () {
    $response = $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'Review the school plan', 'model' => 'gpt-6-sol'])
        ->assertOk()->assertJsonPath('token_quota.credits_used', 20);
    $conversation = Conversation::findOrFail($response->json('conversation_id'));
    expect($conversation->chat_model)->toBe('gpt-6-sol')
        ->and(UsageLog::first()->model)->toBe('gpt-6-sol')
        ->and(config('openai.model'))->toBe('gpt-6-luna');
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'gpt-6-sol' && $request['reasoning_effort'] === 'none'
        && str_contains($request['messages'][0]['content'], 'GPT-6 Sol'));
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $conversation->id, 'message' => 'Continue with the school plan'])
        ->assertOk()->assertJsonPath('token_quota.credits_used', 40);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $conversation->id, 'message' => 'Shorten the school plan', 'model' => 'gpt-6-luna'])
        ->assertOk()->assertJsonPath('token_quota.credits_used', 41);
    expect($conversation->fresh()->chat_model)->toBe('gpt-6-luna');
});

it('rejects unsupported or disabled models before any prompt or usage is saved', function () {
    foreach (['gpt-6.1-sol', 'gpt-6-astra', 'untrusted-model'] as $model) {
        $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'School task', 'model' => $model])
            ->assertUnprocessable()->assertJsonValidationErrors('model');
    }
    config(['openai.sol_enabled' => false]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'School task', 'model' => 'gpt-6-sol'])
        ->assertUnprocessable();
    $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'School task', 'specialist_model' => 'gpt-6-sol'])
        ->assertUnprocessable()->assertJsonValidationErrors('specialist_model');
    Http::assertNothingSent();
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('usage_logs', 0);
});

it('does not change the model or read history of someone elses conversation', function () {
    $conversation = User::factory()->create()->conversations()->create(['title' => 'Private', 'type' => Conversation::TYPE_CHAT, 'chat_model' => 'gpt-6-luna']);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $conversation->id, 'message' => 'School task', 'model' => 'gpt-6-sol'])
        ->assertNotFound();
    expect($conversation->fresh()->chat_model)->toBe('gpt-6-luna');
    Http::assertNothingSent();
});

it('blocks the next chat after Sol exhausts the shared credit balance', function () {
    $this->user->update(['token_quota_limit' => 20000]);
    UsageLog::create(['user_id' => $this->user->id, 'activity_title' => 'Sol', 'source' => 'agent_workspace', 'model' => 'gpt-6-sol', 'prompt_tokens' => 1000]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'School task', 'model' => 'gpt-6-luna'])
        ->assertStatus(429)->assertSee('Credits');
    Http::assertNothingSent();
});

it('renders model selection and credits and restores the conversations selected model', function () {
    $conversation = $this->user->conversations()->create(['title' => 'Sol chat', 'type' => Conversation::TYPE_CHAT, 'chat_model' => 'gpt-6-sol']);
    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()->assertViewHas('activeChatModel', 'gpt-6-sol')
        ->assertSee('id="chat-model"', false)->assertSee('id="specialist-model"', false)
        ->assertViewHas('activeSpecialistModel', 'gpt-6-luna')->assertSee('GPT-6 Sol')->assertSee('20,000 credits used');
    $this->get(route('ai-plus.my-usage.index'))->assertOk()->assertSee('20,000 of 20,000 credits remaining');
});

it('lets an admin set fractional credit quotas without changing raw-token storage', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('admin.users.token-quota.update'), [
        'user_ids' => [$this->user->id], 'action' => 'set', 'credit_quota_limit' => '123.456',
    ])->assertRedirect();
    expect($this->user->fresh()->token_quota_limit)->toBe(123456);
    $this->actingAs($this->user->fresh())->get(route('ai-plus.my-usage.index'))->assertSee('123.456 of 123.456 credits remaining');
});

it('shows weighted credit totals separately from actual tokens in the admin month filter', function () {
    UsageLog::create(['user_id' => $this->user->id, 'activity_title' => 'Sol', 'source' => 'agent_workspace', 'model' => 'gpt-6-sol', 'prompt_tokens' => 1000]);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.usage.index'))
        ->assertOk()->assertViewHas('summary', fn (array $summary): bool => $summary['total_tokens'] === 1000 && $summary['total_quota_units'] === 20000)
        ->assertSee('20,000')->assertSee('1,000 actual tokens');
});

it('records streamed Sol usage and preserves the chosen model', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $conversation = $this->user->conversations()->create(['title' => 'Stream', 'type' => Conversation::TYPE_CHAT]);
    Http::fake(['https://api.openai.com/*' => Http::response(
        'data: '.json_encode(['model' => 'gpt-6-sol', 'choices' => [['delta' => ['content' => 'Streamed Sol answer']]]])."\n\n"
        .'data: '.json_encode(['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 700, 'completion_tokens' => 300]])."\n\n"
        ."data: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'],
    )]);
    $response = $this->post(route('ai-plus.agent-workspace.send-stream'), ['conversation_id' => $conversation->id, 'message' => 'Review the school plan', 'model' => 'gpt-6-sol'])->assertOk();
    $bufferLevel = ob_get_level();
    try {
        $response->baseResponse->getCallback()();
    } finally {
        while (ob_get_level() < $bufferLevel) {
            ob_start();
        }
    }
    expect($conversation->messages()->where('role', 'assistant')->first()->content)->toBe('Streamed Sol answer')
        ->and(UsageLog::first()->model)->toBe('gpt-6-sol')
        ->and(app(TokenQuotaService::class)->summary($this->user)['credits_used'])->toBe(20.0);
});

it('keeps Sol file creation and confirmation on Sol and bills both once', function (string $format) {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Storage::fake('ai-artifacts');
    $conversation = $this->user->conversations()->create(['title' => 'Export', 'type' => Conversation::TYPE_CHAT]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()->push([
        'model' => 'gpt-6-sol', 'choices' => [['message' => ['content' => '', 'tool_calls' => [[
            'id' => 'call-word', 'type' => 'function', 'function' => ['name' => 'create_ai_artifact', 'arguments' => json_encode([
                'format' => $format, 'filename' => 'school-plan', 'content' => "# Complete school plan\n\n## Learning objectives\n\n- Review the complete school objectives.", 'summary' => 'File ready.',
            ])],
        ]]]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ])->push([
        'model' => 'gpt-6-sol', 'choices' => [['message' => ['content' => 'AI_PLUS_FILE_ACTION_CONFIRMED']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ])]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $conversation->id, 'message' => 'Create a '.$format.' file for the school plan', 'model' => 'gpt-6-sol'])
        ->assertOk()->assertJsonCount(1, 'artifacts')->assertJsonPath('token_quota.credits_used', 0.6);
    Http::assertSentCount(2);
    expect(UsageLog::count())->toBe(1)->and(UsageLog::first()->quota_units)->toBe(600);
    Http::assertNotSent(fn (Request $request): bool => $request['model'] !== 'gpt-6-sol');
})->with(['word', 'excel', 'powerpoint', 'pdf', 'html']);

it('uses Sol for the coordinator and Luna for its specialist and charges each model rate', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Storage::fake('knowledge');
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Reviewer']);
    $parent->connectedAgents()->attach($child);
    $conversation = $this->user->conversations()->create(['title' => 'School plan', 'agent_id' => $parent->id, 'type' => Conversation::TYPE_CHAT]);
    $makeResponse = fn (string $content, array $calls = [], string $model = 'gpt-6-sol'): array => [
        'model' => $model, 'choices' => [['message' => ['content' => $content, 'tool_calls' => $calls]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ];
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push($makeResponse('', [['id' => 'consult', 'type' => 'function', 'function' => [
            'name' => 'consult_connected_agent', 'arguments' => json_encode(['agent_id' => $child->id, 'task' => 'Review the school objectives']),
        ]]], 'gpt-6-sol'))->push($makeResponse('Specialist findings', [], 'gpt-6-luna'))->push($makeResponse('Final school plan', [], 'gpt-6-sol'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $conversation->id, 'message' => 'Review the school plan', 'model' => 'gpt-6-sol'])
        ->assertOk()->assertJsonPath('token_quota.credits_used', 0.615);
    expect(UsageLog::count())->toBe(3)
        ->and(UsageLog::orderBy('id')->pluck('model')->all())->toBe(['gpt-6-sol', 'gpt-6-luna', 'gpt-6-sol'])
        ->and(UsageLog::orderBy('id')->pluck('quota_units')->all())->toBe([300, 15, 300])
        ->and(UsageLog::sum('quota_units'))->toBe(615)
        ->and($conversation->messages()->where('role', 'assistant')->first()->prompt_tokens)->toBe(30);
    Http::assertSentCount(3);
    expect(Http::recorded()->map(fn (array $pair): string => $pair[0]['model'])->all())
        ->toBe(['gpt-6-sol', 'gpt-6-luna', 'gpt-6-sol']);
});

it('saves one specialist model per conversation and applies its Sol credit rate', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Storage::fake('knowledge');
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Reviewer']);
    $parent->connectedAgents()->attach($child);
    $conversation = $this->user->conversations()->create(['title' => 'School plan', 'agent_id' => $parent->id, 'type' => Conversation::TYPE_CHAT]);
    $makeResponse = fn (string $content, array $calls = [], string $model = 'gpt-6-luna'): array => [
        'model' => $model, 'choices' => [['message' => ['content' => $content, 'tool_calls' => $calls]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ];
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push($makeResponse('', [['id' => 'consult', 'type' => 'function', 'function' => [
            'name' => 'consult_connected_agent', 'arguments' => json_encode(['agent_id' => $child->id, 'task' => 'Review the school objectives']),
        ]]]))
        ->push($makeResponse('Specialist findings', [], 'gpt-6-sol'))
        ->push($makeResponse('Final school plan'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $conversation->id,
        'message' => 'Review the school plan',
        'model' => 'gpt-6-luna',
        'specialist_model' => 'gpt-6-sol',
    ])->assertOk()->assertJsonPath('token_quota.credits_used', 0.33);

    expect($conversation->fresh()->specialist_model)->toBe('gpt-6-sol')
        ->and(UsageLog::orderBy('id')->pluck('model')->all())->toBe(['gpt-6-luna', 'gpt-6-sol', 'gpt-6-luna'])
        ->and(UsageLog::orderBy('id')->pluck('quota_units')->all())->toBe([15, 300, 15]);
    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()->assertViewHas('activeSpecialistModel', 'gpt-6-sol');
});

it('rejects excessive credit precision and lets admins reset credit quotas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->postJson(route('admin.users.token-quota.update'), [
        'user_ids' => [$this->user->id], 'action' => 'set', 'credit_quota_limit' => '1.0001',
    ])->assertUnprocessable()->assertJsonValidationErrors('credit_quota_limit');
    $this->user->update(['token_quota_limit' => 123456]);
    $this->post(route('admin.users.token-quota.update'), ['user_ids' => [$this->user->id], 'action' => 'reset', 'credit_quota_limit' => null])->assertRedirect();
    expect($this->user->fresh()->token_quota_limit)->toBeNull();
});

it('exports the transcript with the selected model and records its credit charge', function () {
    Storage::fake('ai-artifacts');
    $conversation = $this->user->conversations()->create(['title' => 'School transcript', 'type' => Conversation::TYPE_CHAT]);
    $conversation->messages()->createMany([
        ['role' => 'user', 'content' => 'Write the school plan'],
        ['role' => 'assistant', 'content' => 'The complete school plan'],
    ]);
    $this->postJson(route('ai-plus.conversations.export', $conversation), [
        'format' => 'word', 'language' => 'source', 'template' => 'standard', 'filename' => 'school-plan', 'model' => 'gpt-6-sol',
    ])->assertOk()->assertJsonPath('token_quota.credits_used', 20);
    expect($conversation->fresh()->chat_model)->toBe('gpt-6-sol')
        ->and(UsageLog::first()->model)->toBe('gpt-6-sol')
        ->and(UsageLog::first()->quota_units)->toBe(20000);
});

it('reports Sol permission errors without silently using Luna or charging a failed answer', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    config(['openai.retry_times' => 1, 'openai.model_access_retry_times' => 1]);
    Http::fake(['https://api.openai.com/*' => Http::response(['error' => [
        'code' => 'model_not_found', 'message' => 'Project does not have access to model gpt-6-sol',
    ]], 403)]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'Review the school plan', 'model' => 'gpt-6-sol'])
        ->assertStatus(502);
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request['model'] !== 'gpt-6-sol');
    $this->assertDatabaseCount('usage_logs', 0);
});
