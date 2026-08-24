<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AiPlusController extends Controller
{
    public function index()
    {
        // MOCK DATA — sau này thay bằng query thật (vd từ bảng ai_plus_modules,
        // hoặc từ config nếu 7 mục này ít thay đổi và không cần quản trị qua DB)
        $createCards = [
            [
                'icon' => 'AW',
                'title' => 'Agent Workspace',
                'description' => "Build, test, and run your own AI agents and automations — connected securely to the school's approved AI tool.",
                'ctaLabel' => 'Open workspace',
                'url' => route('ai-plus.agent-workspace.index'),
            ],
            [
                'icon' => 'PL',
                'title' => 'Prompt Library',
                'description' => 'Ready-to-use prompts for common tasks, organized by role and subject. Copy, tweak, and go.',
                'ctaLabel' => 'Browse prompts',
                'url' => route('ai-plus.prompt-library.index'),
            ],
            [
                'icon' => 'AT',
                'title' => 'Agent Templates',
                'description' => 'Pre-built agents you can clone and customize, instead of starting from a blank page.',
                'ctaLabel' => 'View templates',
                'url' => route('ai-plus.agent-templates.index'),
            ],
        ];

        $communityCards = [
            [
                'icon' => 'SH',
                'title' => 'Sharing & Showcase',
                'description' => 'Browse agents and AI projects built by colleagues across LSTS. Leave a comment, ask how it works, or get inspired for your own.',
                'strip' => ['12 departments', 'New this week', 'Open for feedback'], // mock số liệu — thay bằng COUNT() thật sau này
                'ctaLabel' => 'Explore showcase',
                'url' => route('ai-plus.sharing-showcase.index'),
            ],
            [
                'icon' => 'SU',
                'title' => 'Support',
                'description' => "Something not working, or not sure where to start? Report an issue, ask a question, or request a new feature.",
                'strip' => [],
                'ctaLabel' => 'Get help',
                'url' => route('ai-plus.support.index'),
            ],
        ];

        $guidanceCards = [
            [
                'icon' => 'PG',
                'title' => 'AI Policy & Guidelines',
                'description' => "What's allowed, what isn't, and how to keep student and parent data safe when using AI at LSTS.",
                'ctaLabel' => 'Read the policy',
                'url' => route('ai-plus.ai-policy.index'),
            ],
            [
                'icon' => 'MU',
                'title' => 'My Usage',
                'description' => 'See your own AI activity — agents created, prompts run, and where your time has been saved.',
                'ctaLabel' => 'View my activity',
                'url' => route('ai-plus.my-usage.index'),
            ],
        ];

        return view('ai-plus.index', [
            'createCards' => $createCards,
            'communityCards' => $communityCards,
            'guidanceCards' => $guidanceCards,
            'viewingAs' => 'Teacher / Staff', // sau này lấy từ auth()->user()->role
        ]);
    }
}
