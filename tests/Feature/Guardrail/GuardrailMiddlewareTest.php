<?php

use App\Http\Middleware\GuardrailMiddleware;
use App\Services\Guardrail\RegexPiiFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses()->group('guardrail', 'feature');

describe('GuardrailMiddleware', function () {
    beforeEach(function () {
        $this->middleware = new GuardrailMiddleware(new RegexPiiFilter());
        Route::get('ai-plus/test', fn () => 'ok')->name('ai-plus.test');
        Route::post('ai-plus/chat', fn () => 'ok')->name('ai-plus.chat');
        Route::post('other/route', fn () => 'ok')->name('other.route');
    });

    test('skips GET requests', function () {
        $request = Request::create('/ai-plus/chat', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->getContent())->toBe('ok');
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });

    test('skips non-ai-plus routes', function () {
        $request = Request::create('/other/route', 'POST', ['content' => 'SĐT: 0901234567']);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->getContent())->toBe('ok');
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });

    test('skips whitelisted ai-plus paths (agent-templates)', function () {
        $request = Request::create('/ai-plus/agent-templates', 'POST', ['content' => 'SĐT: 0901234567']);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });

    test('skips whitelisted ai-plus paths (prompt-library)', function () {
        $request = Request::create('/ai-plus/prompt-library', 'POST', ['content' => 'SĐT: 0901234567']);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });

    test('filters PII on ai-plus chat route (JSON)', function () {
        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => 'SĐT em là 0901234567']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response($req->input('content')));

        expect($response->headers->get('X-PII-Filtered'))->toBe('true')
            ->and($response->headers->get('X-PII-Count'))->toBe('1')
            ->and($response->getContent())->toContain('[SĐT]')
            ->and($response->getContent())->not->toContain('0901234567');
    });

    test('filters PII on ai-plus chat route (form data)', function () {
        $request = Request::create('/ai-plus/chat', 'POST', ['content' => 'Email: test@school.edu.vn']);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response($req->input('content')));

        expect($response->headers->get('X-PII-Filtered'))->toBe('true')
            ->and($response->getContent())->toContain('[EMAIL]')
            ->and($response->getContent())->not->toContain('test@school.edu.vn');
    });

    test('filters PII with message key', function () {
        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['message' => 'HS12345678 đến lớp']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response($req->input('message')));

        expect($response->headers->get('X-PII-Filtered'))->toBe('true')
            ->and($response->getContent())->toContain('[MÃ_HS]');
    });

    test('filters PII with prompt key', function () {
        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['prompt' => 'CCCD: 079200001234']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response($req->input('prompt')));

        expect($response->headers->get('X-PII-Filtered'))->toBe('true')
            ->and($response->getContent())->toContain('[CCCD]');
    });

    test('logs PII detection to guardrail channel', function () {
        Log::spy('guardrail');

        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => 'SĐT: 0901234567']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $this->middleware->handle($request, fn ($req) => response('ok'));

        Log::shouldHaveReceived('info')->with('PII detected and filtered', fn ($context) =>
            isset($context['user_id']) &&
            isset($context['route']) &&
            $context['route'] === 'ai-plus.chat' &&
            in_array('phone_vn', $context['detected_types']) &&
            $context['detected_count'] === 1
        );
    });

    test('does not log actual PII values', function () {
        Log::spy('guardrail');

        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => 'SĐT: 0901234567, email: secret@test.com']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $this->middleware->handle($request, fn ($req) => response('ok'));

        Log::shouldHaveReceived('info');
        $loggedCall = Log::captured()['guardrail'][0] ?? null;
        $context = $loggedCall[1] ?? [];

        // Should NOT contain actual PII
        expect(json_encode($context))->not->toContain('0901234567')
            ->and(json_encode($context))->not->toContain('secret@test.com');
    });

    test('handles empty content gracefully', function () {
        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => '']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });

    test('handles missing content key gracefully', function () {
        $request = Request::create('/ai-plus/chat', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['other_key' => 'value']));
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $this->middleware->handle($request, fn ($req) => response('ok'));
        expect($response->headers->has('X-PII-Filtered'))->toBeFalse();
    });
});