<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptLibraryPrompt;
use App\Models\AgentTemplate;
use App\Models\ShowcasePost;
use App\Models\Faq;
use App\Models\SupportTicket;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'prompts' => PromptLibraryPrompt::count(),
            'templates' => AgentTemplate::count(),
            'showcases' => ShowcasePost::count(),
            'faqs' => Faq::count(),
            'tickets_open' => SupportTicket::where('status', 'pending')->count(),
            'tickets_total' => SupportTicket::count(),
            'users' => User::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}