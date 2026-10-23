<?php

use App\Models\AdminAuditLog;
use App\Models\AiArtifact;
use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\TokenQuotaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function connectedAgentResponse(string $content = '', array $calls = []): array
{
    return [
        'choices' => [['message' => ['content' => $content, 'tool_calls' => $calls]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        'model' => 'gpt-6-luna',
    ];
}

function connectedAgentCall(int $id, string $task = 'Review the school learning objectives', string $callId = 'call-review'): array
{
    return ['id' => $callId, 'type' => 'function', 'function' => [
        'name' => 'consult_connected_agent', 'arguments' => json_encode(['agent_id' => $id, 'task' => $task]),
    ]];
}

function connectedAgentStream(array $response): string
{
    $message = $response['choices'][0]['message'];
    if ($message['tool_calls'] !== []) {
        $message['tool_calls'] = array_map(fn (array $call, int $index): array => ['index' => $index, ...$call], $message['tool_calls'], array_keys($message['tool_calls']));
    }

    return 'data: '.json_encode(['choices' => [['delta' => $message]], 'model' => 'gpt-6-luna'])."\n\n"
        .'data: '.json_encode(['choices' => [['delta' => [], 'finish_reason' => $message['tool_calls'] === [] ? 'stop' : 'tool_calls']], 'usage' => $response['usage']])."\n\n"
        ."data: [DONE]\n\n";
}

beforeEach(function () {
    config(['agent_connections.enabled' => true, 'openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->parentAgent = $this->user->agents()->create(['title' => 'Coordinator', 'system_prompt' => 'Coordinate school tasks']);
    $this->childAgent = $this->user->agents()->create(['title' => 'Reviewer', 'system_prompt' => 'CHILD_INSTRUCTIONS: review learning objectives']);
    $this->parentAgent->connectedAgents()->attach($this->childAgent, ['role' => 'Check learning objectives']);
    $this->conversation = $this->user->conversations()->create(['title' => 'School planning', 'agent_id' => $this->parentAgent->id, 'type' => Conversation::TYPE_CHAT]);
    Storage::fake('knowledge');
    Storage::fake('ai-artifacts');
    Http::preventStrayRequests();
});

it('delegates then synthesizes without creating files or extra conversations and bills every call once', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse('REVIEW_FINDINGS'))
        ->push(connectedAgentResponse('FINAL_ANSWER'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
        ->assertOk()->assertJsonPath('reply', "FINAL_ANSWER\n\n🤖 **Agents consulted:** Reviewer.");
    expect(Conversation::count())->toBe(1)->and($this->conversation->messages()->count())->toBe(2)
        ->and(AiArtifact::count())->toBe(0)
        ->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(45)
        ->and($this->conversation->messages()->where('role', 'assistant')->first()->prompt_tokens)->toBe(30);
    Http::assertSent(function (Request $request): bool {
        $messages = $request['messages'];

        return collect($messages)->contains(fn (array $message): bool => ($message['role'] ?? '') === 'tool'
            && ($message['tool_call_id'] ?? '') === 'call-review' && str_contains($message['content'], 'REVIEW_FINDINGS'))
            && collect($messages)->contains(fn (array $message): bool => ($message['role'] ?? '') === 'assistant' && isset($message['tool_calls']));
    });
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'CHILD_INSTRUCTIONS') && ! isset($request['tools']) && $request['max_completion_tokens'] === 2048);
    expect(AdminAuditLog::where('event', 'agent.consulted')->first()->metadata)->toBe(['agent_id' => $this->childAgent->id, 'status' => 'completed']);
});

it('uses child Knowledge without passing unrelated conversation history', function () {
    $this->childAgent->update(['knowledge' => json_encode([['path' => 'guide.txt', 'original_name' => 'guide.txt']])]);
    KnowledgeChunk::create(['agent_id' => $this->childAgent->id, 'source_file' => 'guide.txt', 'chunk_index' => 0, 'content' => 'Learning objectives: KNOWLEDGE_MARKER', 'embedding' => []]);
    $this->conversation->messages()->create(['role' => 'user', 'content' => 'UNRELATED_HISTORY_MARKER']);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse('Findings from Knowledge'))
        ->push(connectedAgentResponse('Final'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'KNOWLEDGE_MARKER')
        && ! str_contains(json_encode($request['messages']), 'UNRELATED_HISTORY_MARKER'));
});

it('consults ten configured specialists sequentially and synthesizes after the tenth', function () {
    $children = collect([$this->childAgent]);
    foreach (range(2, 10) as $index) {
        $child = $this->user->agents()->create(['title' => 'Specialist '.$index]);
        $this->parentAgent->connectedAgents()->attach($child);
        $children->push($child);
    }
    $sequence = Http::sequence();
    foreach ($children as $index => $child) {
        $sequence->push(connectedAgentResponse('', [connectedAgentCall($child->id, 'Review school planning section '.($index + 1), 'call-specialist-'.$index)]));
        $sequence->push(connectedAgentResponse('Findings from specialist '.($index + 1)));
    }
    $sequence->push(connectedAgentResponse('TEN_AGENT_FINAL_ANSWER'));
    Http::fake(['https://api.openai.com/*' => $sequence]);
    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id, 'message' => 'Create a personalized school lesson using all ten specialists',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'TEN_AGENT_FINAL_ANSWER') && str_contains($reply, 'Specialist 10'));
    Http::assertSentCount(21);
    expect(AdminAuditLog::where('event', 'agent.consulted')->count())->toBe(10)
        ->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(315);
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->count() === 10
        && ! collect($request['tools'] ?? [])->contains(fn (array $tool): bool => $tool['function']['name'] === 'consult_connected_agent'));
});

it('rejects unconfigured Agent calls without reading their private prompts', function () {
    $foreign = User::factory()->create()->agents()->create(['title' => 'Private', 'system_prompt' => 'NEVER_SEND_THIS']);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($foreign->id)]))
        ->push(connectedAgentResponse('Could not consult that Agent.'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'NEVER_SEND_THIS'));
});

it('does not consult an Agent twice or follow its nested connections', function () {
    $grandchild = $this->user->agents()->create(['title' => 'Nested Agent']);
    $this->childAgent->connectedAgents()->attach($grandchild);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id), connectedAgentCall($this->childAgent->id, 'Repeat task', 'call-duplicate')]))
        ->push(connectedAgentResponse('Findings'))
        ->push(connectedAgentResponse('Final'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSentCount(3);
    expect(AdminAuditLog::where('event', 'agent.consulted')->count())->toBe(1);
});

it('preserves billed usage if the specialist and final synthesis fail', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(['error' => ['message' => 'Unavailable']], 503)
        ->push(['error' => ['message' => 'Unavailable']], 503)]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertStatus(502);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15)->and(AiArtifact::count())->toBe(0);
    Http::assertSentCount(3);
});

it('stops further calls when token quota is exhausted', function () {
    $this->user->update(['token_quota_limit' => 15]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
        ->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'token limit'));
    Http::assertSentCount(1);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15);
});

it('streams coordination progress and persists the final answer', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentStream(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)])), 200, ['Content-Type' => 'text/event-stream'])
        ->push(connectedAgentResponse('Review findings'))
        ->push(connectedAgentStream(connectedAgentResponse('FINAL_STREAMED')), 200, ['Content-Type' => 'text/event-stream'])]);
    $response = $this->post(route('ai-plus.agent-workspace.send-stream'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan']);
    $response->assertOk();
    // The SSE response flushes every output buffer. Restore PHPUnit's buffer
    // depth afterwards so it can continue collecting subsequent test output.
    $bufferLevel = ob_get_level();
    $response->baseResponse->getCallback()();
    while (ob_get_level() < $bufferLevel) {
        ob_start();
    }
    expect($this->conversation->messages()->where('role', 'assistant')->first()->content)->toContain('FINAL_STREAMED')->toContain('Agents consulted');
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(45);
});

it('creates exactly one final Word artifact and includes file-confirmation usage', function () {
    $artifactCall = ['id' => 'call-word', 'type' => 'function', 'function' => [
        'name' => 'create_ai_artifact', 'arguments' => json_encode([
            'format' => 'word', 'file_extension' => '', 'filename' => 'school-plan',
            'content' => '# School plan with REVIEWED_CONTENT', 'summary' => 'Reviewed school plan ready.',
        ]),
    ]];
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse('REVIEWED_CONTENT'))
        ->push(connectedAgentResponse('', [$artifactCall]))
        ->push(connectedAgentResponse('AI_PLUS_FILE_ACTION_CONFIRMED'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan and create a Word file'])
        ->assertOk()->assertJsonCount(1, 'artifacts');
    expect(AiArtifact::count())->toBe(1)->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(60);
    $artifact = AiArtifact::first();
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    expect($zip->getFromName('word/document.xml'))->toContain('REVIEWED_CONTENT');
    $zip->close();
});

it('recovers from a specialist error without inventing a file or losing quota usage', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(['error' => ['message' => 'Unavailable']], 503)
        ->push(connectedAgentResponse('Reviewer was unavailable. Here is what I can answer.'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
        ->assertOk()->assertJsonPath('reply', 'Reviewer was unavailable. Here is what I can answer.');
    expect(AiArtifact::count())->toBe(0)->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(30);
});

it('retains specialist usage even if the final coordinating request fails', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse('Completed review'))
        ->push(['error' => ['message' => 'Unavailable']], 503)]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertStatus(502);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(30)->and(AiArtifact::count())->toBe(0);
});

it('defers output tools proposed alongside consultation until a final coordinating response', function () {
    $prematureFile = ['id' => 'call-premature', 'type' => 'function', 'function' => [
        'name' => 'create_ai_artifact', 'arguments' => json_encode(['format' => 'word', 'content' => 'Premature file']),
    ]];
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id), $prematureFile]))
        ->push(connectedAgentResponse('Completed review'))
        ->push(connectedAgentResponse('Here is the review in chat.'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    expect(AiArtifact::count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->contains(fn (array $message): bool => ($message['tool_call_id'] ?? '') === 'call-premature'
        && str_contains($message['content'], 'separate tool call')));
});

it('rechecks a shared specialist permission after the coordinator proposes a call', function () {
    $this->childAgent->update(['user_id' => User::factory()->create()->id, 'is_shared' => true, 'sharing_access' => 'use_only']);
    $requestCount = 0;
    Http::fake(['https://api.openai.com/*' => function () use (&$requestCount) {
        if (++$requestCount === 1) {
            $this->childAgent->update(['is_shared' => false]);

            return Http::response(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]));
        }

        return Http::response(connectedAgentResponse('The shared Agent is unavailable.'));
    }]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'CHILD_INSTRUCTIONS'));
});

it('does not start a request when the coordination time or token budget has been reached', function (string $setting) {
    config([$setting => 0]);
    Http::fake();
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
        ->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'time or token limit'));
    Http::assertNothingSent();
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(0);
})->with(['agent_connections.timeout_seconds', 'agent_connections.max_turn_tokens']);

it('allows coordination past ninety seconds and bounds each call by the time remaining', function (bool $streaming) {
    config(['openai.timeout' => 120]);
    $this->travelTo(now()->startOfSecond());
    $timeouts = [];
    $phpLimits = [];
    Http::fake(['https://api.openai.com/*' => function (Request $request, array $options) use (&$timeouts, &$phpLimits) {
        $timeouts[] = $options['timeout'];
        $phpLimits[] = (int) ini_get('max_execution_time');
        $callNumber = count($timeouts);
        if ($callNumber <= 2) {
            $this->travel(100)->seconds();
        }
        $payload = match ($callNumber) {
            1 => connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]),
            2 => connectedAgentResponse('Findings after two hundred seconds'),
            default => connectedAgentResponse('LONG_COORDINATION_COMPLETE'),
        };

        return ($request['stream'] ?? false)
            ? Http::response(connectedAgentStream($payload), 200, ['Content-Type' => 'text/event-stream'])
            : Http::response($payload);
    }]);
    try {
        $data = ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'];
        $response = $streaming
            ? $this->post(route('ai-plus.agent-workspace.send-stream'), $data)
            : $this->postJson(route('ai-plus.agent-workspace.send'), $data);
        $response->assertOk();
        if ($streaming) {
            $bufferLevel = ob_get_level();
            $response->baseResponse->getCallback()();
            while (ob_get_level() < $bufferLevel) {
                ob_start();
            }
        }
    } finally {
        $this->travelBack();
    }
    expect($timeouts)->toBe([120, 120, 100])->and($phpLimits)->toBe([420, 420, 420]);
    expect($this->conversation->messages()->where('role', 'assistant')->first()->content)->toContain('LONG_COORDINATION_COMPLETE');
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(45);
})->with(['JSON chat' => false, 'streaming chat' => true]);

it('stops new calls after the five minute coordination deadline without losing usage', function () {
    $this->travelTo(now()->startOfSecond());
    Http::fake(['https://api.openai.com/*' => function () {
        $this->travel(301)->seconds();

        return Http::response(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]));
    }]);
    try {
        $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
            ->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'time or token limit'));
    } finally {
        $this->travelBack();
    }
    Http::assertSentCount(1);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15);
});

it('preserves ordinary PHP and model timeouts when collaboration is not used', function (string $mode) {
    config(['openai.timeout' => 37]);
    if ($mode === 'disabled') {
        config(['agent_connections.enabled' => false]);
    } elseif ($mode === 'quick chat') {
        $this->conversation->update(['agent_id' => null]);
    } else {
        $this->parentAgent->connectedAgents()->detach();
    }
    $timeouts = [];
    $phpLimits = [];
    Http::fake(['https://api.openai.com/*' => function (Request $request, array $options) use (&$timeouts, &$phpLimits) {
        $timeouts[] = $options['timeout'];
        $phpLimits[] = (int) ini_get('max_execution_time');

        return Http::response(connectedAgentResponse('Ordinary answer'));
    }]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    expect($timeouts)->toBe([37])->and($phpLimits)->toBe([120]);
})->with(['disabled', 'quick chat', 'no connections']);

it('does not call a specialist after the turn token threshold is reached by the coordinator', function () {
    config(['agent_connections.max_turn_tokens' => 15]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSentCount(1);
    expect(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15);
});

it('preserves the higher completion budget for long inputs even with connections configured', function () {
    $this->conversation->messages()->create(['role' => 'user', 'content' => str_repeat('School planning context. ', 190)]);
    $this->conversation->messages()->create(['role' => 'assistant', 'content' => str_repeat('School planning details. ', 190)]);
    config(['openai.long_output_input_chars' => 6000]);
    Http::fake(['https://api.openai.com/*' => Http::response(connectedAgentResponse('Long answer'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSent(fn (Request $request): bool => $request['max_completion_tokens'] === 16384);
});

it('does not execute a malformed consultation', function () {
    $badCall = connectedAgentCall($this->childAgent->id);
    $badCall['function']['arguments'] = '{invalid json';
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$badCall]))
        ->push(connectedAgentResponse('Please clarify the review task.'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertOk();
    Http::assertSentCount(2);
    expect(AdminAuditLog::where('event', 'agent.consulted')->count())->toBe(0);
});

it('keeps legacy chat behavior when connections are disabled', function () {
    config(['agent_connections.enabled' => false]);
    Http::fake(['https://api.openai.com/*' => Http::response(connectedAgentResponse('Legacy response'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertJsonPath('reply', 'Legacy response');
    Http::assertNotSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'consult_connected_agent'));
    expect(UsageLog::count())->toBe(1)->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15);
    expect($this->parentAgent->connectedAgents()->count())->toBe(1);
});
