<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TokenQuotaService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class MyUsageController extends Controller
{
    public function index(Request $request, TokenQuotaService $tokenQuotaService)
    {
        /** @var User $user */
        $user = $request->user();
        $logs = $user->usageLogs();
        $tokenQuota = $tokenQuotaService->summary($user);
        $chartDays = (int) $request->integer('range', 7);
        $chartDays = in_array($chartDays, [7, 30], true) ? $chartDays : 7;

        $totalTokens = (clone $logs)->selectRaw('SUM(prompt_tokens + completion_tokens) as total')->value('total') ?? 0;

        $stats = [
            'prompts' => (clone $logs)->count(),
            'tokens' => $this->formatTokens($totalTokens),
            // Giả định ước lượng: mỗi prompt tiết kiệm trung bình ~0.9 phút — CHƯA có số liệu
            // xác thực, cần hiệu chỉnh lại sau khi có khảo sát/dữ liệu Pilot thật.
            'timeSaved' => round(((clone $logs)->count() * 0.9) / 60, 1).'h',
            'agentsCreated' => $user->agents()->count(),
            'agentsShared' => $user->agents()->where('is_shared', true)->count(),
        ];

        $activities = (clone $logs)->whereNull('hidden_at')->latest()->take(5)->get()->map(function ($log) {
            return [
                'icon' => $log->source === 'template_used' ? '📋' : '💬',
                'title' => $log->activity_title,
                'source' => $log->source === 'template_used' ? 'Template used' : 'Agent Workspace',
                'time' => $this->formatRelativeTime($log->created_at),
                'tokens' => number_format($log->prompt_tokens + $log->completion_tokens).' tok',
                'isTemplate' => $log->source === 'template_used',
                'workspaceUrl' => $log->related_conversation_id
                    ? route('ai-plus.agent-workspace.index', ['conversation_id' => $log->related_conversation_id])
                    : null,
            ];
        })->toArray();

        $topTasks = (clone $logs)
            ->whereNotNull('task_category')
            ->selectRaw('task_category, COUNT(*) as cnt')
            ->groupBy('task_category')
            ->orderByDesc('cnt')
            ->take(5)
            ->get()
            ->map(fn ($row) => [
                'icon' => $this->iconForCategory($row->task_category),
                'name' => $row->task_category,
                'count' => $row->cnt,
            ])->toArray();

        $chartStart = now()->startOfDay()->subDays($chartDays - 1);
        $dailyUsage = (clone $logs)
            ->where('created_at', '>=', $chartStart)
            ->selectRaw('DATE(created_at) as usage_date, COUNT(*) as prompts, COALESCE(SUM(prompt_tokens + completion_tokens), 0) as tokens')
            ->groupBy('usage_date')
            ->orderBy('usage_date')
            ->get()
            ->keyBy('usage_date');

        $usageChart = collect(range(0, $chartDays - 1))
            ->map(function (int $offset) use ($chartStart, $dailyUsage): array {
                $date = $chartStart->copy()->addDays($offset);
                $usage = $dailyUsage->get($date->toDateString());

                return [
                    'label' => $date->format('M j'),
                    'prompts' => (int) ($usage->prompts ?? 0),
                    'tokens' => (int) ($usage->tokens ?? 0),
                ];
            });

        return view('ai-plus.my-usage.index', [
            'stats' => $stats,
            'activities' => $activities,
            'topTasks' => $topTasks,
            'viewingAs' => 'Teacher / Staff',
            'userName' => $user->name,
            'userInitials' => $user->initials,
            'userRole' => $user->role,
            'tokenQuota' => $tokenQuota,
            'chartDays' => $chartDays,
            'usageChart' => $usageChart,
        ]);
    }

    private function formatTokens(int $tokens): string
    {
        return $tokens >= 1000 ? round($tokens / 1000, 1).'K' : (string) $tokens;
    }

    private function formatRelativeTime(CarbonInterface $time): string
    {
        if ($time->isToday()) {
            return $time->format('h:i A');
        }
        if ($time->isYesterday()) {
            return 'Yesterday';
        }

        return $time->diffInDays(now()).' days ago';
    }

    private function iconForCategory(string $category): string
    {
        return match ($category) {
            'Email drafting' => '📧',
            'Lesson planning' => '📚',
            'Assessment creation' => '📝',
            'Data analysis' => '📊',
            'Brainstorming' => '💡',
            default => '💬',
        };
    }
}
