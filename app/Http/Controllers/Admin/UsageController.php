<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ImageGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function index(Request $request)
    {
        $phase = (string) config('usage.phase', 'testing');
        $limits = config('usage.token_limits', []);
        $limit = max((int) ($limits[$phase] ?? $limits['testing'] ?? 20_000_000), 1);
        $cycleStartedAt = now()->startOfMonth();

        if ($phaseStartedAt = config('usage.phase_started_at')) {
            $phaseStartedAt = Carbon::parse($phaseStartedAt);
            if ($phaseStartedAt->greaterThan($cycleStartedAt)) {
                $cycleStartedAt = $phaseStartedAt;
            }
        }

        $usageTotal = UsageLog::query()
            ->selectRaw('COALESCE(SUM(prompt_tokens + completion_tokens), 0)')
            ->whereColumn('usage_logs.user_id', 'users.id')
            ->where('created_at', '>=', $cycleStartedAt);

        $baseQuery = User::query()
            ->select('users.*')
            ->selectSub($usageTotal, 'used_tokens');

        $allUsage = (clone $baseQuery)->get();
        $summary = [
            'users' => $allUsage->count(),
            'active_users' => $allUsage->where('is_active', true)->count(),
            'total_tokens' => $allUsage->sum(fn (User $user) => (int) $user->used_tokens),
            'near_limit' => $allUsage->filter(fn (User $user) => ((int) $user->used_tokens / max((int) ($user->token_quota_limit ?? $limit), 1)) >= 0.85)->count(),
        ];

        $users = $baseQuery
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            }))
            ->orderByDesc('used_tokens')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $imageModels = ImageGenerationService::modelOptions();
        $imageRows = UsageLog::query()
            ->where('created_at', '>=', $cycleStartedAt)
            ->whereIn('model', array_keys($imageModels))
            ->selectRaw('model, COUNT(*) as images, COALESCE(SUM(prompt_tokens + completion_tokens), 0) as tokens')
            ->groupBy('model')
            ->get()
            ->keyBy('model');
        $imageUsage = collect($imageModels)->map(fn (array $details, string $model) => [
            'label' => $details['label'],
            'images' => (int) ($imageRows[$model]->images ?? 0),
            'tokens' => (int) ($imageRows[$model]->tokens ?? 0),
        ]);
        $topImageUsers = UsageLog::query()
            ->where('created_at', '>=', $cycleStartedAt)
            ->whereIn('model', array_keys($imageModels))
            ->selectRaw('user_id, COUNT(*) as images, COALESCE(SUM(prompt_tokens + completion_tokens), 0) as tokens')
            ->groupBy('user_id')
            ->orderByDesc('images')
            ->limit(5)
            ->with('user:id,name,email')
            ->get();

        return view('admin.usage.index', [
            'users' => $users,
            'limit' => $limit,
            'phase' => $phase,
            'monthName' => now()->format('F'),
            'summary' => $summary,
            'imageUsage' => $imageUsage,
            'topImageUsers' => $topImageUsers,
        ]);
    }
}
