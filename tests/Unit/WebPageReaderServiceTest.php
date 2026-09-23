<?php

use App\Services\WebPageReaderService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['openai.web_browser_rendering_enabled' => false]);
});

function webReaderWithPublicDns(): WebPageReaderService
{
    return new class extends WebPageReaderService
    {
        protected function resolvePublicIpv4(string $host): ?string
        {
            return $host === 'example.com' ? '93.184.216.34' : null;
        }
    };
}

it('reads readable text from an explicitly supplied public link', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><head><title>Example</title></head><body><h1>Welcome</h1><p>Useful public information.</p><script>alert(1)</script></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $blocks = webReaderWithPublicDns()->readFromMessage('Please summarise https://example.com/article');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0])->toContain('Web page content from https://example.com/article')
        ->toContain('Welcome')
        ->toContain('Useful public information.')
        ->not->toContain('alert(1)');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/article'
        && str_contains((string) $request->header('User-Agent')[0], 'AI-Plus-LSTS-WebReader'));
});

it('does not request private-network links', function () {
    Http::fake();

    $blocks = (new WebPageReaderService)->readFromMessage('Read http://127.0.0.1:8000/admin please');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0])->toContain('Only publicly reachable HTTP(S) pages can be read.');

    Http::assertNothingSent();
});

it('limits each message to two unique links', function () {
    Http::fake([
        'https://example.com/*' => Http::response('Read me', 200, ['Content-Type' => 'text/plain']),
    ]);

    $blocks = webReaderWithPublicDns()->readFromMessage('https://example.com/one https://example.com/two https://example.com/three');

    expect($blocks)->toHaveCount(2);
    Http::assertSentCount(2);
});
