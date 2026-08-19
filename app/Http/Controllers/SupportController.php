<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index()
    {
        $faqs = [
            [
                'question' => 'How do I get started with AI+?',
                'answer' => 'Start by visiting the Agent Workspace where you can chat with AI. Try using a prompt from the Prompt Library to get familiar with how it works. For structured tasks, check out Agent Templates which provide pre-configured setups for common use cases like lesson planning or email drafting.',
            ],
            [
                'question' => 'What is my daily prompt quota?',
                'answer' => 'Teachers and staff have a daily quota of 50 prompts. This resets at midnight (Vietnam time). You can check your remaining quota in the "My Usage" section. If you need more for a specific project, contact the CIEC team.',
            ],
            [
                'question' => 'Can I share my AI conversations with colleagues?',
                'answer' => 'Yes! You can export conversations and share them. Better yet, if you\'ve created a useful agent or prompt, consider sharing it through the Sharing & Showcase section so others can benefit from your work.',
            ],
            [
                'question' => 'Is my data safe? Who can see my conversations?',
                'answer' => 'Your conversations are processed through our secure middleware that filters personal information. Conversations are logged for 30 days for troubleshooting purposes but are not accessible to other users. Admin access is limited to authorized IT personnel for system maintenance only.',
            ],
            [
                'question' => 'Why did I get a "quota exceeded" message?',
                'answer' => 'This means you\'ve used all your daily prompts. The quota resets at midnight. If you consistently need more, please contact CIEC to discuss your use case — we may be able to adjust your quota based on legitimate educational needs.',
            ],
            [
                'question' => 'Can I use AI+ for student assessments?',
                'answer' => 'Yes, but please follow the AI Assessment Policy (3-tier system). For Tier 1 assessments (no AI allowed), do not use AI+. For Tier 2 and 3, use AI+ as specified in the assessment guidelines. Always review the AI Policy & Guidelines section for detailed rules.',
            ],
        ];

        return view('ai-plus.support.index', [
            'faqs' => $faqs,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }
}
