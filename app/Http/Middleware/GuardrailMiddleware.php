<?php

namespace App\Http\Middleware;

use App\Services\Guardrail\RegexPiiFilter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GuardrailMiddleware
{
    public function __construct(
        private RegexPiiFilter $piiFilter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to AI+ routes that accept user input (POST/PUT/PATCH with content)
        if (!$this->shouldFilter($request)) {
            return $next($request);
        }

        $content = $this->extractContent($request);

        if (empty($content)) {
            return $next($request);
        }

        // Layer 1: Regex PII filter
        $result = $this->piiFilter->filter($content, ['replace' => true]);

        if ($result['has_pii']) {
            // Log detected PII for audit (without storing the actual PII)
            $this->logPiiDetection($request, $result['detected']);

            // Replace request content with filtered version
            $this->replaceRequestContent($request, $result['filtered']);

            // Add header to inform client that content was filtered
            $response = $next($request);
            $response->headers->set('X-PII-Filtered', 'true');
            $response->headers->set('X-PII-Count', count($result['detected']));

            return $response;
        }

        return $next($request);
    }

    /**
     * Determine if this request should be filtered.
     */
    private function shouldFilter(Request $request): bool
    {
        // Only filter write operations on AI+ routes
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return false;
        }

        // Only filter routes under ai-plus prefix
        if (!str_starts_with($request->route()?->uri() ?? '', 'ai-plus/')) {
            return false;
        }

        // Skip certain endpoints that don't accept user chat content
        $skipPaths = [
            'ai-plus/agent-templates',
            'ai-plus/prompt-library',
            'ai-plus/sharing-showcase',
            'ai-plus/support',
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($request->route()?->uri() ?? '', $skipPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract content from request (supports JSON and form data).
     */
    private function extractContent(Request $request): ?string
    {
        // Try JSON content first
        if ($request->isJson()) {
            $data = $request->json()->all();
            return $data['content'] ?? $data['message'] ?? $data['prompt'] ?? null;
        }

        // Try form data
        return $request->input('content') ?? $request->input('message') ?? $request->input('prompt') ?? null;
    }

    /**
     * Replace request content with filtered version.
     */
    private function replaceRequestContent(Request $request, string $filtered): void
    {
        if ($request->isJson()) {
            $data = $request->json()->all();
            $key = array_key_first(array_intersect(['content', 'message', 'prompt'], array_keys($data)));
            if ($key) {
                $data[$key] = $filtered;
                $request->replace($data);
            }
        } else {
            $key = array_key_first(array_intersect(['content', 'message', 'prompt'], array_keys($request->all())));
            if ($key) {
                $request->request->set($key, $filtered);
            }
        }
    }

    /**
     * Log PII detection for audit trail.
     */
    private function logPiiDetection(Request $request, array $detected): void
    {
        $user = $request->user();
        Log::channel('guardrail')->info('PII detected and filtered', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'detected_types' => array_column($detected, 'type'),
            'detected_count' => count($detected),
            // Intentionally NOT logging original PII values
        ]);
    }
}