<?php

use App\Http\Middleware\MeasureChatPerformance;
use App\Models\Agent;
use App\Models\KnowledgeChunk;
use App\Models\Message;
use App\Models\SchoolKnowledgeChunk;
use App\Models\SchoolKnowledgeSource;
use App\Models\User;
use App\Services\ChatPerformance;
use App\Services\KnowledgeService;
use App\Services\SchoolKnowledgeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    config(['openai.api_key' => '']);
    Storage::fake('knowledge');
    Storage::fake('chat-attachments');
    Storage::fake('ai-artifacts');
    $this->actingAs($this->user);
    Http::preventStrayRequests();
});

it('does not call embeddings when school and agent knowledge have no vectors', function () {
    config(['openai.api_key' => 'test-key', 'openai.rag_embeddings_enabled' => true]);
    Http::fake();
    $agent = Agent::create(['user_id' => $this->user->id, 'title' => 'Sample agent']);
    KnowledgeChunk::create(['agent_id' => $agent->id, 'source_file' => 'sample.txt', 'chunk_index' => 0, 'content' => 'Staff leave procedure.', 'embedding' => []]);
    $source = SchoolKnowledgeSource::create(['type' => 'upload', 'title' => 'Sample handbook', 'status' => 'ready']);
    SchoolKnowledgeChunk::create(['school_knowledge_source_id' => $source->id, 'chunk_index' => 0, 'content' => 'Staff leave procedure.', 'embedding' => []]);

    expect(app(KnowledgeService::class)->retrieveContext($agent, 'staff leave'))->toContain('Staff leave procedure.');
    expect(app(KnowledgeService::class)->retrieveContextResult($agent, 'staff leave')['context'])->toContain('Staff leave procedure.');
    expect(app(SchoolKnowledgeService::class)->retrieveContext('staff leave'))->toContain('Staff leave procedure.');
    Http::assertNothingSent();
});

it('shares the same query embedding between school and agent retrieval', function () {
    config(['openai.api_key' => 'test-key', 'openai.rag_embeddings_enabled' => true]);
    Http::fake(['*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [1.0]]]])]);
    $agent = Agent::create(['user_id' => $this->user->id, 'title' => 'Sample agent']);
    KnowledgeChunk::create(['agent_id' => $agent->id, 'source_file' => 'sample.txt', 'chunk_index' => 0, 'content' => 'Staff leave procedure.', 'embedding' => [1.0]]);
    $source = SchoolKnowledgeSource::create(['type' => 'upload', 'title' => 'Sample handbook', 'status' => 'ready']);
    SchoolKnowledgeChunk::create(['school_knowledge_source_id' => $source->id, 'chunk_index' => 0, 'content' => 'Staff leave procedure.', 'embedding' => [1.0]]);

    app(KnowledgeService::class)->retrieveContextResult($agent, 'staff leave');
    app(SchoolKnowledgeService::class)->retrieveContext('staff leave');
    Http::assertSentCount(1);
});

it('reads the PDF once while retaining attachment PII protection', function () {
    Process::fake(['*' => Process::result('Ordinary sample instructions.')]);
    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Summarize this school document.',
        'documents' => [['name' => 'sample.pdf', 'data_url' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 sample')]],
    ])->assertOk();
    Process::assertRanTimes(fn () => true, 1);
});

it('still blocks student identifiers from cached PDF extraction before saving a message', function () {
    $records = implode("\n", array_map(fn (int $n) => (string) (1000000 + $n), range(1, 12)));
    Process::fake(['*' => Process::result("Danh sách mã số học sinh\n".$records)]);
    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Summarize this school document.',
        'documents' => [['name' => 'sample.pdf', 'data_url' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 sample')]],
    ])->assertStatus(422)->assertJsonPath('blocked', true);
    expect(Message::count())->toBe(0);
    Http::assertNothingSent();
});

it('logs stage timings for a successful chat without logging the prompt', function () {
    Log::spy();
    $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Create a school lesson about UNIQUE_PRIVATE_SAMPLE.'])->assertOk();
    Log::shouldHaveReceived('info')->with('AI+ chat performance', Mockery::on(function (array $context): bool {
        expect($context['status'])->toBe(200)
            ->and($context['failed'])->toBeFalse()
            ->and($context['stages_ms'])->toHaveKeys(['validation_and_pii', 'moderation', 'knowledge_retrieval', 'prepare_turn', 'ai_response', 'file_output'])
            ->and(json_encode($context))->not->toContain('UNIQUE_PRIVATE_SAMPLE');

        return $context['total_ms'] >= 0;
    }))->once();
});

it('logs failed validation timings with the actual HTTP status', function () {
    Log::spy();
    $this->postJson('/ai-plus/agent-workspace/send', ['message' => str_repeat('x', 5001)])->assertStatus(422);
    Log::shouldHaveReceived('info')->with('AI+ chat performance', Mockery::on(fn (array $context): bool => $context['status'] === 422 && $context['failed']))->once();
});

it('finishes stream metrics after the callback and marks a streaming exception as failed', function () {
    Log::spy();
    $performance = app(ChatPerformance::class);
    $middleware = app(MeasureChatPerformance::class);
    $response = $middleware->handle(request(), fn () => response()->stream(function () use ($performance): void {
        $performance->measure('ai_response', fn () => throw new RuntimeException('Sample failure'));
    }));
    Log::shouldNotHaveReceived('info');
    expect(fn () => $response->sendContent())->toThrow(RuntimeException::class, 'Sample failure');
    Log::shouldHaveReceived('info')->with('AI+ chat performance', Mockery::on(fn (array $context): bool => $context['failed'] && isset($context['stages_ms']['ai_response'])))->once();
});
