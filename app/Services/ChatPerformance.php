<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatPerformance
{
    public function __construct(private readonly Request $request) {}

    public function start(): void
    {
        $this->request->attributes->set('ai_plus.performance', [
            'request_id' => (string) Str::uuid(),
            'started_at' => hrtime(true),
            'stages_ms' => [],
            'failed' => false,
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function measure(string $stage, callable $operation): mixed
    {
        $start = hrtime(true);
        try {
            return $operation();
        } finally {
            $this->record($stage, $start);
        }
    }

    public function record(string $stage, int $startedAt): void
    {
        $state = $this->request->attributes->get('ai_plus.performance');
        if (is_array($state)) {
            $state['stages_ms'][$stage] = ($state['stages_ms'][$stage] ?? 0) + (hrtime(true) - $startedAt) / 1_000_000;
            $this->request->attributes->set('ai_plus.performance', $state);
        }
    }

    public function fail(): void
    {
        $state = $this->request->attributes->get('ai_plus.performance');
        if (is_array($state)) {
            $state['failed'] = true;
            $this->request->attributes->set('ai_plus.performance', $state);
        }
    }

    public function finish(int $status): void
    {
        $state = $this->request->attributes->get('ai_plus.performance');
        if (! is_array($state)) {
            return;
        }
        $this->request->attributes->remove('ai_plus.performance');

        try {
            Log::info('AI+ chat performance', [
                'request_id' => $state['request_id'],
                'route' => $this->request->route()?->getName(),
                'status' => $status,
                'failed' => $state['failed'] || $status >= 400,
                'total_ms' => round((hrtime(true) - $state['started_at']) / 1_000_000, 1),
                'stages_ms' => array_map(fn (float $ms): float => round($ms, 1), $state['stages_ms']),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            ]);
        } catch (Throwable) {
            // Observability must not turn a successful chat into an error.
        }
    }
}
