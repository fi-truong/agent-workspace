<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class TokenQuotaService
{
    public const USAGE_UNITS_SQL = 'COALESCE(quota_units, prompt_tokens + completion_tokens)';

    /**
     * @return array{phase: string, used: int, limit: null, remaining: null, credits_used: float, credits_limit: null, credits_remaining: null, percentage: null, unlimited: true, cycle_started_at: string, month_name: string}
     */
    public function summary(User $user): array
    {
        $phase = (string) config('usage.phase', 'testing');
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
            'limit' => null,
            'remaining' => null,
            'credits_used' => ChatModelService::credits($used),
            'credits_limit' => null,
            'credits_remaining' => null,
            'percentage' => null,
            'unlimited' => true,
            'cycle_started_at' => $cycleStartedAt->toDateTimeString(),
            'month_name' => now()->format('F'),
        ];
    }
}
