<?php

use App\Services\EmbeddingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['openai.api_key' => 'test-key', 'openai.rag_embeddings_enabled' => true, 'openai.retry_delay_ms' => 0]);
    Http::preventStrayRequests();
});

it('shares a query embedding across service instances within a request only', function () {
    Http::fake(['*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [1.0, 0.0]]]])]);
    expect(app(EmbeddingService::class)->embed('staff leave'))->toEqual([1.0, 0.0]);
    expect(app(EmbeddingService::class)->embed('staff leave'))->toEqual([1.0, 0.0]);
    Http::assertSentCount(1);

    app()->instance('request', Request::create('/new-request'));
    app(EmbeddingService::class)->embed('staff leave');
    Http::assertSentCount(2);
});

it('does not reuse embeddings for a different input or model', function () {
    Http::fake(['*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [1.0]]]])]);
    app(EmbeddingService::class)->embed('first question');
    app(EmbeddingService::class)->embed('second question');
    config(['openai.embedding_model' => 'different-test-model']);
    app(EmbeddingService::class)->embed('first question');
    Http::assertSentCount(3);
});

it('logs HTTP rejection as an HTTP failure and avoids repeating it in the same request', function () {
    Http::fake(['*/embeddings' => Http::response(['error' => ['message' => 'private provider detail']], 403)]);
    Log::spy();
    expect(app(EmbeddingService::class)->embed('private query'))->toBe([]);
    expect(app(EmbeddingService::class)->embed('private query'))->toBe([]);
    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once()->with('Embedding API request failed; using keyword RAG.', ['status' => 403]);
    Log::shouldNotHaveReceived('warning', ['Embedding API connection failed; using keyword RAG.', Mockery::any()]);
});

it('distinguishes connection failures and retains the configured connection retry', function () {
    Http::fake(['*/embeddings' => Http::failedConnection()]);
    Log::spy();
    expect(app(EmbeddingService::class)->embed('sample question'))->toBe([]);
    Log::shouldHaveReceived('warning')->once()->with('Embedding API connection failed; using keyword RAG.', [
        'exception' => ConnectionException::class,
    ]);
});
