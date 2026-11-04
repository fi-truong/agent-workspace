<?php

use App\Models\AdminAuditLog;
use App\Models\AiArtifact;
use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\KnowledgeChunk;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ConversationSourceContextService;
use App\Services\TokenQuotaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function connectedAgentResponse(string $content = '', array $calls = [], ?string $finishReason = null): array
{
    return [
        'choices' => [['message' => ['content' => $content, 'tool_calls' => $calls], 'finish_reason' => $finishReason ?? ($calls === [] ? 'stop' : 'tool_calls')]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        'model' => 'gpt-6-luna',
    ];
}

function connectedAgentCall(int $id, string $task = 'Review the school learning objectives', string $callId = 'call-review', ?array $sourceIds = null, bool $includePreviousResults = true): array
{
    return ['id' => $callId, 'type' => 'function', 'function' => [
        'name' => 'consult_connected_agent', 'arguments' => json_encode([
            'agent_id' => $id, 'task' => $task,
            'source_ids' => $sourceIds, 'include_previous_results' => $includePreviousResults,
        ]),
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

function requestHasContent(Request $request, string $marker): bool
{
    return str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $marker);
}

beforeEach(function () {
    config(['agent_connections.enabled' => true, 'openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false, 'openai.max_tokens' => 8192]);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->parentAgent = $this->user->agents()->create(['title' => 'Coordinator', 'system_prompt' => 'Coordinate school tasks']);
    $this->childAgent = $this->user->agents()->create(['title' => 'Reviewer', 'system_prompt' => 'CHILD_INSTRUCTIONS: review learning objectives']);
    $this->parentAgent->connectedAgents()->attach($this->childAgent, ['role' => 'Check learning objectives']);
    $this->conversation = $this->user->conversations()->create(['title' => 'School planning', 'agent_id' => $this->parentAgent->id, 'type' => Conversation::TYPE_CHAT]);
    Storage::fake('knowledge');
    Storage::fake('ai-artifacts');
    Storage::fake('chat-attachments');
    Http::preventStrayRequests();
});

it('delegates then synthesizes without creating files or extra conversations and bills every call once', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse('REVIEW_FINDINGS'))
        ->push(connectedAgentResponse('FINAL_ANSWER'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])
        ->assertOk()->assertJsonPath('reply', "FINAL_ANSWER\n\n🤖 **Agents returned results:** Reviewer.");
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
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'CHILD_INSTRUCTIONS') && ! isset($request['tools']) && $request['max_completion_tokens'] === 8192 && $request['reasoning_effort'] === 'none');
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
    expect($this->conversation->messages()->where('role', 'assistant')->first()->content)->toContain('FINAL_STREAMED')->toContain('Agents returned results');
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
        ->assertOk()->assertJsonPath('reply', "Reviewer was unavailable. Here is what I can answer.\n\n⚠️ **Agent result missing:** Reviewer did not return a usable result; its findings were not included in the summary.\n\nThe AI service could not complete this Agent call. Please try again later.");
    expect(AiArtifact::count())->toBe(0)->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(30);
});

it('reports complete source handoff when a specialist call fails after receiving a long attachment', function () {
    $source = str_repeat("Teaching context and assessment criteria for the class.\n", 280)
        ."FINAL_SOURCE_MARKER: The last section is available to the specialist.\n";
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Review the attached guide')]))
        ->push(['error' => ['message' => 'Unavailable']], 503)
        ->push(connectedAgentResponse('The specialist request failed.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the attached guide.',
        'documents' => [[
            'name' => 'teaching-guide.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode($source),
        ]],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agent result missing')
        && str_contains($reply, 'The AI service could not complete this Agent call.')
        && str_contains($reply, 'You do not need to upload the same files again.')
        && ! str_contains($reply, 'Agent source coverage'));

    expect(ConversationAttachment::where('original_name', 'teaching-guide.txt')->value('extraction_complete'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'FINAL_SOURCE_MARKER'));
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], '"handoff_complete":true')
            && str_contains($message['content'], 'teaching-guide.txt'),
    ));
});

it('records usage and source coverage when a specialist returns no text', function (string $finishReason, string $failureKind, string $guidance) {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Analyze the attached results')]))
        ->push([
            'choices' => [['message' => ['content' => ''], 'finish_reason' => $finishReason]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 2048],
            'model' => 'gpt-6-luna',
        ])
        ->push(connectedAgentResponse('The specialist did not return findings.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Analyze the attached results.',
        'documents' => [[
            'name' => 'results.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode('SYNTHETIC_RESULTS_MARKER: three sample records.'),
        ]],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agent result missing')
        && str_contains($reply, $guidance)
        && str_contains($reply, 'You do not need to upload the same files again.')
        && ! str_contains($reply, 'Agents returned results'));

    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->first()->completion_tokens)->toBe(2048)
        ->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(2098)
        ->and(AdminAuditLog::where('event', 'agent.consulted')->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'SYNTHETIC_RESULTS_MARKER')
        && $request['reasoning_effort'] === 'none');
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], '"failure_kind":"'.$failureKind.'"')
            && str_contains($message['content'], '"handoff_complete":true'),
    ));
})->with([
    ['length', 'output_limit', 'reached its response limit'],
    ['stop', 'empty_response', 'returned an empty result'],
]);

it('continues a specialist answer cut by the per-request output limit', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse("ROW_1\n", [], 'length'))
        ->push(connectedAgentResponse("ROW_2\n"))
        ->push(connectedAgentResponse('I received the complete review.'))]);

    $response = $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the school plan and summarize the result.',
    ]);
    $response->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agents returned results:** Reviewer.')
        && ! str_contains($reply, 'specialist result reached an output limit'));

    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], '"status":"completed"')
            && str_contains($message['content'], 'ROW_1')
            && str_contains($message['content'], 'ROW_2'),
    ));
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->contains(
        fn (array $message): bool => ($message['role'] ?? '') === 'assistant' && ($message['content'] ?? '') === "ROW_1\n",
    ) && collect($request['messages'])->contains(
        fn (array $message): bool => ($message['role'] ?? '') === 'user'
            && is_string($message['content'] ?? null)
            && str_contains($message['content'], 'Continue the same answer'),
    ));
    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->count())->toBe(2)
        ->and(AdminAuditLog::where('event', 'agent.consulted')->first()->metadata)->toBe(['agent_id' => $this->childAgent->id, 'status' => 'completed']);
});

it('passes a long complete specialist result to the coordinator in one request', function () {
    $rows = implode("\n", array_map(fn (int $number): string => sprintf('S%02d\tGroup %d\tEvidence %d', $number, $number % 4, $number), range(1, 32)));
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse("Student\tGroup\tEvidence\n".$rows))
        ->push(connectedAgentResponse('All 32 rows are available for review.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review all student records.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'All 32 rows are available'));

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        && str_contains($request['messages'][0]['content'] ?? '', 'compact rows')
        && $request['max_completion_tokens'] === 8192);
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], 'S01')
            && str_contains($message['content'], 'S32')
            && str_contains($message['content'], '"status":"completed"'),
    ));
});

it('shows received specialist findings when the deadline expires before synthesis', function (string $finishReason, string $expectedStatus) {
    $this->travelTo(now()->startOfSecond());
    $requestCount = 0;
    Http::fake(['https://api.openai.com/*' => function () use (&$requestCount, $finishReason) {
        $requestCount++;
        if ($requestCount === 1) {
            return Http::response(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]));
        }
        $this->travel(301)->seconds();

        return Http::response(connectedAgentResponse('RECEIVED_FINDINGS', [], $finishReason));
    }]);
    try {
        $this->postJson(route('ai-plus.agent-workspace.send'), [
            'conversation_id' => $this->conversation->id,
            'message' => 'Review the school plan.',
        ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'RECEIVED_FINDINGS')
            && str_contains($reply, $expectedStatus)
            && str_contains($reply, 'No final file was created.'));
    } finally {
        $this->travelBack();
    }

    Http::assertSentCount(2);
    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->count())->toBe(1);
})->with([
    ['stop', 'complete specialist result'],
    ['length', 'partial specialist result'],
]);

it('reports a specialist result as partial if continuation still reaches the bounded limit', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse("ROW_1\n", [], 'length'))
        ->push(connectedAgentResponse("ROW_2\n", [], 'length'))
        ->push(connectedAgentResponse("ROW_3\n", [], 'length'))
        ->push(connectedAgentResponse("ROW_4\n", [], 'length'))
        ->push(connectedAgentResponse('I received only part of the review.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the school plan and summarize the result.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'specialist result reached an output limit'));

    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->count())->toBe(4)
        ->and(AdminAuditLog::where('event', 'agent.consulted')->first()->metadata)->toBe(['agent_id' => $this->childAgent->id, 'status' => 'partial']);
    Http::assertSentCount(6);
});

it('shows received specialist findings when the turn token threshold is reached', function () {
    $specialist = connectedAgentResponse('TOKEN_LIMIT_FINDINGS');
    $specialist['usage'] = ['prompt_tokens' => 20, 'completion_tokens' => 32000];
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push($specialist)]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the school plan.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'TOKEN_LIMIT_FINDINGS')
        && str_contains($reply, 'complete specialist result'));

    Http::assertSentCount(2);
    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->first()->completion_tokens)->toBe(32000);
});

it('keeps time for coordinator synthesis instead of starting a late specialist continuation', function () {
    $this->travelTo(now()->startOfSecond());
    $requestCount = 0;
    Http::fake(['https://api.openai.com/*' => function () use (&$requestCount) {
        $requestCount++;
        if ($requestCount === 1) {
            return Http::response(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]));
        }
        if ($requestCount === 2) {
            $this->travel(200)->seconds();

            return Http::response(connectedAgentResponse('FIRST_SECTION', [], 'length'));
        }

        return Http::response(connectedAgentResponse('The partial findings need review.'));
    }]);
    try {
        $this->postJson(route('ai-plus.agent-workspace.send'), [
            'conversation_id' => $this->conversation->id,
            'message' => 'Review the school plan.',
        ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'The partial findings need review.')
            && str_contains($reply, 'specialist result reached an output limit'));
    } finally {
        $this->travelBack();
    }

    Http::assertSentCount(3);
    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->count())->toBe(1);
});

it('keeps the received specialist pages and their usage if a continuation request fails', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id)]))
        ->push(connectedAgentResponse("FIRST_PAGE_MARKER\n", [], 'length'))
        ->push(['error' => ['message' => 'Unavailable']], 503)
        ->push(connectedAgentResponse('Only partial findings were available.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the school plan and summarize the result.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'specialist continuation failed'));

    expect(UsageLog::where('activity_title', 'Connected Agent consultation')->count())->toBe(1)
        ->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(45);
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], '"status":"partial"')
            && str_contains($message['content'], 'FIRST_PAGE_MARKER'),
    ));
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
    expect($this->conversation->messages()->where('role', 'assistant')->latest('id')->value('content'))
        ->toContain('Agent result missing')
        ->not->toContain('Agents returned results');
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

it('hands multiple uploaded files and an image to a specialist from the same chat turn', function () {
    $childCall = connectedAgentCall($this->childAgent->id, 'Compare the lesson plan with the assessment criteria', 'call-source-review');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The lesson plan matches the supplied assessment criteria.'))
        ->push(connectedAgentResponse('The review is complete.'))]);

    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII=';
    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Compare the lesson plan and criteria.',
        'images' => ['data:image/png;base64,'.$png],
        'documents' => [
            ['name' => 'lesson-plan.txt', 'data_url' => 'data:text/plain;base64,'.base64_encode("LESSON_OBJECTIVE_MARKER\nStudents compare evidence.")],
            ['name' => 'assessment.csv', 'data_url' => 'data:text/csv;base64,'.base64_encode("Criterion,Description\nEVIDENCE_CRITERION_MARKER,Uses evidence")],
        ],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agents returned results'));

    expect(ConversationAttachment::where('conversation_id', $this->conversation->id)->count())->toBe(3)
        ->and(ConversationAttachment::where('source_type', 'document')->where('status', 'available')->count())->toBe(2);
    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request['messages'][0]['content'] ?? '', JSON_UNESCAPED_UNICODE), 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'LESSON_OBJECTIVE_MARKER')
        && requestHasContent($request, 'EVIDENCE_CRITERION_MARKER')
        && str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE), 'image_url'));
});

it('hands only selected authorized sources to a specialist doing an independent check', function () {
    $sourceMessage = $this->conversation->messages()->create(['role' => 'user', 'content' => 'Saved source files']);
    $raw = ConversationAttachment::create([
        'conversation_id' => $this->conversation->id, 'message_id' => $sourceMessage->id,
        'user_id' => $this->user->id, 'original_name' => 'raw-results.txt',
        'source_type' => 'document', 'status' => 'available',
        'extracted_text' => 'RAW_RESULTS_MARKER', 'character_count' => 18,
        'extraction_complete' => true,
    ]);
    ConversationAttachment::create([
        'conversation_id' => $this->conversation->id, 'message_id' => $sourceMessage->id,
        'user_id' => $this->user->id, 'original_name' => 'reference-answer.txt',
        'source_type' => 'document', 'status' => 'available',
        'extracted_text' => 'REFERENCE_ANSWER_MARKER', 'character_count' => 23,
        'extraction_complete' => true,
    ]);
    $this->conversation->messages()->create([
        'role' => 'assistant', 'content' => 'Earlier reply',
        'internal_context' => ConversationSourceContextService::encodeResults([[
            'agent_title' => 'Earlier Agent', 'task' => 'Earlier task',
            'result' => 'PRIOR_RESULT_MARKER', 'result_complete' => true,
            'source_coverage' => [],
        ]]),
    ]);
    $firstHalf = implode('', array_map(fn (int $row): string => sprintf("R%02d\tG1\n", $row), range(1, 16)));
    $secondHalf = implode('', array_map(fn (int $row): string => sprintf("R%02d\tG2\n", $row), range(17, 32)));
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Check the raw results independently', 'call-raw', [$raw->id], false)]))
        ->push(connectedAgentResponse($firstHalf, [], 'length'))
        ->push(connectedAgentResponse($secondHalf))
        ->push(connectedAgentResponse('Final summary'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Check the raw results independently.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agents returned results'));

    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CONNECTED AGENTS')
        && str_contains($request['messages'][0]['content'], 'raw-results.txt')
        && str_contains($request['messages'][0]['content'], 'reference-answer.txt'));
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'RAW_RESULTS_MARKER')
        && ! requestHasContent($request, 'REFERENCE_ANSWER_MARKER')
        && ! requestHasContent($request, 'PRIOR_RESULT_MARKER'));
    Http::assertSent(fn (Request $request): bool => collect($request['messages'])->where('role', 'tool')->contains(
        fn (array $message): bool => str_contains($message['content'], '"status":"completed"')
            && str_contains($message['content'], 'R01')
            && str_contains($message['content'], 'R32')
            && ! str_contains($message['content'], 'REFERENCE_ANSWER_MARKER'),
    ));
});

it('rejects a selected source that is no longer in the current user catalog', function () {
    $sourceMessage = $this->conversation->messages()->create(['role' => 'user', 'content' => 'Saved source']);
    $foreignSource = ConversationAttachment::create([
        'conversation_id' => $this->conversation->id, 'message_id' => $sourceMessage->id,
        'user_id' => User::factory()->create()->id, 'original_name' => 'private.txt',
        'source_type' => 'document', 'status' => 'available',
        'extracted_text' => 'PRIVATE_SOURCE_MARKER', 'character_count' => 21,
        'extraction_complete' => true,
    ]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Inspect a source', 'call-missing', [$foreignSource->id], false)]))
        ->push(connectedAgentResponse('The selected source was unavailable.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Inspect the saved source.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agent result missing'));

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        || requestHasContent($request, 'PRIVATE_SOURCE_MARKER'));
});

it('lists older attachment excerpts as partial and never calls them complete originals', function () {
    $legacyMessage = $this->conversation->messages()->create([
        'role' => 'user',
        'content' => "Previous upload\n📎 Tệp đính kèm: older-results.txt",
        'internal_context' => 'LEGACY_EXCERPT_MARKER',
    ]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Review the older source', 'call-legacy', [-$legacyMessage->id], false)]))
        ->push(connectedAgentResponse('Only the saved excerpt was available.'))
        ->push(connectedAgentResponse('The old source is incomplete.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the older source.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agent source coverage')
        && str_contains($reply, 'older-results.txt'));

    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CONNECTED AGENTS')
        && str_contains($request['messages'][0]['content'], 'legacy_partial'));
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'] ?? '', 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'LEGACY_EXCERPT_MARKER')
        && ! requestHasContent($request, 'complete content supplied'));
});

it('keeps ordinary chats on their existing attachment storage path', function () {
    $this->parentAgent->connectedAgents()->detach();
    Http::fake(['https://api.openai.com/*' => Http::response(connectedAgentResponse('Ordinary chat reply'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Summarize this note.',
        'documents' => [[
            'name' => 'ordinary-note.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode('ORDINARY_CHAT_SOURCE'),
        ]],
    ])->assertOk()->assertJsonPath('reply', 'Ordinary chat reply');

    expect(ConversationAttachment::count())->toBe(0);
    Http::assertSentCount(1);
});

it('makes an earlier chat attachment available to a later specialist call without another upload', function () {
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('I have saved the uploaded source in this chat.'))
        ->push(connectedAgentResponse('', [connectedAgentCall($this->childAgent->id, 'Find the classroom routine in the earlier source', 'call-later-source')]))
        ->push(connectedAgentResponse('The routine is listed in the source.'))
        ->push(connectedAgentResponse('I found the routine in the earlier upload.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Keep this routine available for later.',
        'documents' => [[
            'name' => 'routine.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode('CLASSROOM_ROUTINE_MARKER: Start with a two-minute reset.'),
        ]],
    ])->assertOk();

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Find the routine from the earlier file.',
    ])->assertOk();

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request['messages'][0]['content'] ?? '', JSON_UNESCAPED_UNICODE), 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'CLASSROOM_ROUTINE_MARKER'));
    expect(ConversationAttachment::where('conversation_id', $this->conversation->id)->where('original_name', 'routine.txt')->exists())->toBeTrue();
});

it('passes completed earlier Agent results to a specialist on a later chat turn', function () {
    $firstCall = connectedAgentCall($this->childAgent->id, 'Inspect the rubric and return its core decision', 'call-first-step');
    $secondCall = connectedAgentCall($this->childAgent->id, 'Apply the earlier decision to the next case', 'call-second-step');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$firstCall]))
        ->push(connectedAgentResponse('PRIOR_STEP_RESULT_MARKER: use the evidence-based rubric.'))
        ->push(connectedAgentResponse('Turn one is complete.'))
        ->push(connectedAgentResponse('', [$secondCall]))
        ->push(connectedAgentResponse('The next case meets the rubric.'))
        ->push(connectedAgentResponse('The saved finding was applied.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the rubric.',
    ])->assertOk();
    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Now apply the earlier result to this case.',
    ])->assertOk();

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request['messages'][0]['content'] ?? '', JSON_UNESCAPED_UNICODE), 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'PRIOR_STEP_RESULT_MARKER'));
    expect($this->conversation->messages()->where('role', 'assistant')->orderByDesc('id')->first()->content)->toContain('Agents returned results');
});

it('selects relevant rows from a long table and explicitly reports partial source coverage', function () {
    config(['agent_connections.handoff_context_chars' => 1600, 'agent_connections.handoff_chunk_chars' => 400]);
    $rows = ['Record,Category,Value'];
    foreach (range(1, 280) as $index) {
        $rows[] = 'ROW-'.$index.',category-'.$index.',ordinary value '.$index;
    }
    $rows[] = 'TARGET-ROW-XQ7,requested category,VALUE_ONLY_IN_RELEVANT_TABLE_ROW';
    $childCall = connectedAgentCall($this->childAgent->id, 'Find the value for TARGET-ROW-XQ7', 'call-long-table');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The requested value is VALUE_ONLY_IN_RELEVANT_TABLE_ROW.'))
        ->push(connectedAgentResponse('The requested value is available.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Find the value for TARGET-ROW-XQ7 in the attached table.',
        'documents' => [[
            'name' => 'records.csv',
            'data_url' => 'data:text/csv;base64,'.base64_encode(implode("\n", $rows)),
        ]],
    ])->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Agent source coverage')
            && str_contains($reply, 'Only selected excerpts fit the handoff context budget'));

    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request['messages'][0]['content'] ?? '', JSON_UNESCAPED_UNICODE), 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'VALUE_ONLY_IN_RELEVANT_TABLE_ROW')
        && ! requestHasContent($request, 'ROW-1,category-1,ordinary value 1'));
});

it('preserves empty spreadsheet cells so delegated table columns do not shift', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['Record ID', 'Notes', 'Amount'],
        ['ROW-WITH-GAP', null, 'COLUMN_ALIGNMENT_MARKER'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'agent-handoff-xlsx-');
    (new Xlsx($spreadsheet))->save($path);
    $binary = file_get_contents($path);
    @unlink($path);

    $childCall = connectedAgentCall($this->childAgent->id, 'Check the amount for ROW-WITH-GAP', 'call-xlsx-gap');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The amount is in the third column.'))
        ->push(connectedAgentResponse('The amount is available.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Check the amount for ROW-WITH-GAP.',
        'documents' => [[
            'name' => 'records.xlsx',
            'data_url' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,'.base64_encode((string) $binary),
        ]],
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => requestHasContent($request, 'ROW-WITH-GAP |  | COLUMN_ALIGNMENT_MARKER'));
});

it('marks source text beyond the private retention limit as incomplete', function () {
    config(['agent_connections.max_saved_source_chars' => 1000]);
    $documentText = "Heading\n".str_repeat("ordinary reference text\n", 200)."TARGET_AFTER_RETENTION_LIMIT\n";
    $childCall = connectedAgentCall($this->childAgent->id, 'Find TARGET_AFTER_RETENTION_LIMIT in the uploaded source', 'call-retention-limit');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The requested text was not included.'))
        ->push(connectedAgentResponse('The retained portion does not include the requested text.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Find TARGET_AFTER_RETENTION_LIMIT in the uploaded source.',
        'documents' => [[
            'name' => 'long-source.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode($documentText),
        ]],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'long-source.txt')
        && str_contains($reply, 'Only the first 1000 characters could be retained'));

    expect(ConversationAttachment::where('original_name', 'long-source.txt')->value('status'))->toBe('partial');
    Http::assertSent(fn (Request $request): bool => requestHasContent($request, 'Only the first 1000 characters could be retained'));
});

it('tells a specialist when an attached file format is unsupported', function () {
    $childCall = connectedAgentCall($this->childAgent->id, 'Review the attached source', 'call-unsupported-file');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The source file cannot be read in this format.'))
        ->push(connectedAgentResponse('I could not read the attached format.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review the attached source.',
        'documents' => [[
            'name' => 'legacy.bin',
            'data_url' => 'data:application/octet-stream;base64,'.base64_encode('UNSUPPORTED_BINARY_MARKER'),
        ]],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'legacy.bin')
        && str_contains($reply, 'not supported for text extraction'));

    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request['messages'][0]['content'] ?? '', JSON_UNESCAPED_UNICODE), 'CHILD_INSTRUCTIONS')
        && requestHasContent($request, 'not supported for text extraction')
        && ! requestHasContent($request, 'UNSUPPORTED_BINARY_MARKER'));
});

it('tells a specialist when an attached PDF has no extractable text', function () {
    $childCall = connectedAgentCall($this->childAgent->id, 'Read the attached PDF', 'call-unreadable-pdf');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The PDF contains no extractable text.'))
        ->push(connectedAgentResponse('I could not read the PDF.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Read the attached PDF.',
        'documents' => [[
            'name' => 'scanned-handout.pdf',
            'data_url' => 'data:application/pdf;base64,'.base64_encode("%PDF-1.4\ninvalid scan fixture\n%%EOF"),
        ]],
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'scanned-handout.pdf')
        && str_contains($reply, 'No readable text was extracted'));

    expect(ConversationAttachment::where('original_name', 'scanned-handout.pdf')->value('status'))->toBe('unreadable');
    Http::assertSent(fn (Request $request): bool => requestHasContent($request, 'scanned-handout.pdf')
        && requestHasContent($request, 'No readable text was extracted'));
});

it('identifies the file rejected by the chat upload size limit', function () {
    Http::fake();
    $payload = 'data:text/plain;base64,'.base64_encode(str_repeat('x', 5 * 1024 * 1024 + 1));

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Review this file.',
        'documents' => [[
            'name' => 'oversized-reference.txt',
            'data_url' => $payload,
        ]],
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'File "oversized-reference.txt" was not read because chat attachments are limited to 5120 KB each. Reduce its size or split it and attach it again.');

    expect($this->conversation->messages()->count())->toBe(0);
    Http::assertNothingSent();
});

it('reports when an earlier attached image is no longer present in private storage', function () {
    $message = $this->conversation->messages()->create([
        'role' => 'user',
        'content' => '📎 Image in this conversation: chart.png',
    ]);
    $path = $this->conversation->id.'/chart.png';
    Storage::disk('chat-attachments')->put($path, 'image bytes');
    ConversationAttachment::create([
        'conversation_id' => $this->conversation->id,
        'message_id' => $message->id,
        'user_id' => $this->user->id,
        'original_name' => 'chart.png',
        'extension' => 'png',
        'source_type' => 'image',
        'status' => 'available',
        'disk' => 'chat-attachments',
        'path' => $path,
        'extraction_complete' => true,
        'metadata' => ['mime_type' => 'image/png'],
    ]);
    Storage::disk('chat-attachments')->delete($path);

    $childCall = connectedAgentCall($this->childAgent->id, 'Read the chart attached earlier', 'call-missing-image');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(connectedAgentResponse('', [$childCall]))
        ->push(connectedAgentResponse('The image file is missing from storage.'))
        ->push(connectedAgentResponse('I could not inspect the missing chart.'))]);

    $this->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Read the chart attached earlier.',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'chart.png')
        && str_contains($reply, 'saved image is missing from private storage'));

    Http::assertSent(fn (Request $request): bool => requestHasContent($request, 'saved image is missing from private storage')
        && ! str_contains(json_encode($request['messages'], JSON_UNESCAPED_UNICODE), 'image_url'));
});

it('never sends a conversation attachment to a different conversation owner', function () {
    Storage::disk('chat-attachments')->put($this->conversation->id.'/secret.txt', 'OWNER_PRIVATE_ATTACHMENT_MARKER');
    ConversationAttachment::create([
        'conversation_id' => $this->conversation->id,
        'message_id' => $this->conversation->messages()->create(['role' => 'user', 'content' => '📎 Tệp đính kèm: secret.txt'])->id,
        'user_id' => $this->user->id,
        'original_name' => 'secret.txt',
        'extension' => 'txt',
        'source_type' => 'document',
        'status' => 'available',
        'extracted_text' => 'OWNER_PRIVATE_ATTACHMENT_MARKER',
        'character_count' => 30,
        'extraction_complete' => true,
    ]);
    $other = User::factory()->create();
    Http::fake();

    $this->actingAs($other)->postJson(route('ai-plus.agent-workspace.send'), [
        'conversation_id' => $this->conversation->id,
        'message' => 'Read this chat file.',
    ])->assertNotFound();

    Http::assertNothingSent();
});

it('keeps legacy chat behavior when connections are disabled', function () {
    config(['agent_connections.enabled' => false]);
    Http::fake(['https://api.openai.com/*' => Http::response(connectedAgentResponse('Legacy response'))]);
    $this->postJson(route('ai-plus.agent-workspace.send'), ['conversation_id' => $this->conversation->id, 'message' => 'Review the school plan'])->assertJsonPath('reply', 'Legacy response');
    Http::assertNotSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'consult_connected_agent'));
    expect(UsageLog::count())->toBe(1)->and(app(TokenQuotaService::class)->summary($this->user)['used'])->toBe(15);
    expect($this->parentAgent->connectedAgents()->count())->toBe(1);
});
