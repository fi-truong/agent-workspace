<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MyUsageController extends Controller
{
    public function index()
    {
        // TODO-SSO-DEPLOY: thay bằng auth()->user() khi deploy server có SSO thật
        $user = User::where('email', 'ciec.coordinator.04@lsts.edu.vn')->first();
        $logs = $user->usageLogs();

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

        $activities = (clone $logs)->latest()->take(5)->get()->map(function ($log) {
            return [
                'icon' => $log->source === 'template_used' ? '📋' : '💬',
                'title' => $log->activity_title,
                'source' => $log->source === 'template_used' ? 'Template used' : 'Agent Workspace',
                'time' => $this->formatRelativeTime($log->created_at),
                'tokens' => number_format($log->prompt_tokens + $log->completion_tokens).' tok',
                'isTemplate' => $log->source === 'template_used',
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

        return view('ai-plus.my-usage.index', [
            'stats' => $stats,
            'activities' => $activities,
            'topTasks' => $topTasks,
            'viewingAs' => 'Teacher / Staff',
            'userName' => $user->name,
            'userInitials' => $user->initials,
            'userRole' => $user->role,
            'promptsUsed' => (clone $logs)->whereDate('created_at', today())->count(),
            'promptsLimit' => $user->daily_prompt_quota,
        ]);
    }

    private function formatTokens(int $tokens): string
    {
        return $tokens >= 1000 ? round($tokens / 1000, 1).'K' : (string) $tokens;
    }

    private function formatRelativeTime(\Carbon\CarbonInterface $time): string
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
