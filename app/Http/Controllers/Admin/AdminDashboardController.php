<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AgentTemplate;
use App\Models\Faq;
use App\Models\PromptLibraryPrompt;
use App\Models\ShowcasePost;
use App\Models\SupportTicket;
use App\Models\UsageLog;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'prompts' => PromptLibraryPrompt::count(),
            'templates' => AgentTemplate::count(),
            'showcases' => ShowcasePost::count(),
            'showcases_pending' => ShowcasePost::where('status', 'pending')->count(),
            'faqs' => Faq::count(),
            'tickets_open' => SupportTicket::where('status', 'pending')->count(),
            'tickets_total' => SupportTicket::count(),
            'users' => User::count(),
            'users_active' => User::where('is_active', true)->count(),
            'usage_7d' => UsageLog::where('created_at', '>=', now()->subDays(7))->count(),
            'usage_30d' => UsageLog::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $recentActivity = AdminAuditLog::with('user')->latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentActivity'));
    }
}
