<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class TokenQuotaService
{
    /**
     * @return array{phase: string, used: int, limit: int, remaining: int, percentage: float, cycle_started_at: string, month_name: string}
     */
    public function summary(User $user): array
    {
        $phase = (string) config('usage.phase', 'testing');
        $limits = config('usage.token_limits', []);
        $limit = max((int) ($limits[$phase] ?? $limits['testing'] ?? 20_000_000), 1);
        $cycleStartedAt = now()->startOfMonth();
        $phaseStartedAt = config('usage.phase_started_at');

        if ($phaseStartedAt && Carbon::parse($phaseStartedAt)->greaterThan($cycleStartedAt)) {
            $cycleStartedAt = Carbon::parse($phaseStartedAt);
        }

        $used = (int) $user->usageLogs()
            ->where('created_at', '>=', $cycleStartedAt)
            ->selectRaw('COALESCE(SUM(prompt_tokens + completion_tokens), 0) as total')
            ->value('total');

        return [
            'phase' => $phase,
            'used' => $used,
            'limit' => $limit,
            'remaining' => max($limit - $used, 0),
            'percentage' => min(($used / $limit) * 100, 100),
            'cycle_started_at' => $cycleStartedAt->toDateTimeString(),
            'month_name' => now()->format('F'),
        ];
    }

    public function isExhausted(User $user): bool
    {
        $summary = $this->summary($user);

        return $summary['used'] >= $summary['limit'];
    }
}
