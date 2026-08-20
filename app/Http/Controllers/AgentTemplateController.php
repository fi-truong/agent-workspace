<?php

namespace App\Http\Controllers;

use App\Models\AgentTemplate;
use Illuminate\Http\Request;

class AgentTemplateController extends Controller
{
    public function index()
    {
        // Đọc từ DB thật (thay cho mảng mock trước đây)
        $templates = AgentTemplate::with('features')->latest()->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'icon' => $t->icon,
                'title' => $t->title,
                'description' => $t->description,
                'features' => $t->features->pluck('feature_text')->toArray(),
                'uses' => $t->uses_count,
                'preview_class' => $t->preview_class,
                'badge' => match ($t->badge) {
                    'popular' => 'Popular',
                    'new' => 'New',
                    default => null,
                },
            ];
        })->toArray();

        return view('ai-plus.agent-templates.index', [
            'templates' => $templates,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }
}
