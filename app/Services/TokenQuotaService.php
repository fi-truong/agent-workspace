<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class TokenQuotaService
{
    public const USAGE_UNITS_SQL = 'COALESCE(quota_units, prompt_tokens + completion_tokens)';

    /**
     * @return array{phase: string, used: int, limit: int, remaining: int, credits_used: float, credits_limit: float, credits_remaining: float, percentage: float, cycle_started_at: string, month_name: string}
     */
    public function summary(User $user): array
    {
        $phase = (string) config('usage.phase', 'testing');
        $limits = config('usage.token_limits', []);
        $defaultLimit = (int) ($limits[$phase] ?? $limits['testing'] ?? 20_000_000);
        $limit = max((int) ($user->token_quota_limit ?? $defaultLimit), 1);
        $cycleStartedAt = now()->startOfMonth();
        $phaseStartedAt = config('usage.phase_started_at');

        if ($phaseStartedAt && Carbon::parse($phaseStartedAt)->greaterThan($cycleStartedAt)) {
            $cycleStartedAt = Carbon::parse($phaseStartedAt);
        }

        $used = (int) $user->usageLogs()
            ->where('created_at', '>=', $cycleStartedAt)
            ->selectRaw('COALESCE(SUM('.self::USAGE_UNITS_SQL.'), 0) as total')
            ->value('total');

        return [
            'phase' => $phase,
            'used' => $used,
            'limit' => $limit,
            'remaining' => max($limit - $used, 0),
            'credits_used' => ChatModelService::credits($used),
            'credits_limit' => ChatModelService::credits($limit),
            'credits_remaining' => ChatModelService::credits(max($limit - $used, 0)),
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

    public function remainingTokens(User $user, ?string $model): int
    {
        return intdiv($this->summary($user)['remaining'], ChatModelService::multiplier($model));
    }
}
