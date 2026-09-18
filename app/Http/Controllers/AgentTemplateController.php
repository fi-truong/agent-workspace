<?php

namespace App\Http\Controllers;

use App\Models\AgentTemplate;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTemplate::with('features')->whereNull('source_agent_id')->latest();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Sort
        $sort = $request->get('sort', 'new');
        if ($sort === 'new') {
            $query->latest();
        } elseif ($sort === 'alpha') {
            $query->orderBy('name');
        } else {
            // fallback for any old 'popular' URLs
            $query->latest();
        }

        // Pagination - 10 per page
        $templatesPaginator = $query->paginate(10)->withQueryString();

        $templates = $templatesPaginator->getCollection()->map(function ($t) {
            return [
                'id' => $t->id,
                'icon' => $t->icon,
                'title' => $t->name,
                'description' => $t->description,
                'features' => $t->features->pluck('feature_text')->toArray(),
                'uses' => $t->uses_count,
                'preview_class' => $t->preview_class,
                'badge' => match ($t->badge) {
                    'popular' => 'Popular',
                    'new' => 'New',
                    default => null,
                },
                'category' => $t->category,
                'is_shared_agent' => $t->source_agent_id !== null,
            ];
        })->toArray();

        // Categories for filter buttons (from DB)
        $categories = AgentTemplate::select('category')->distinct()->whereNotNull('category')->pluck('category')->sort()->values()->toArray();

        // Category display names
        $categoryLabels = [
            'teaching' => '📚 Teaching',
            'assessment' => '📝 Assessment',
            'communication' => '📧 Communication',
            'admin' => '📊 Admin',
            'subject' => '🔬 Subject-Specific',
            'shared' => '👥 Team Shared',
        ];

        // Total count for current filter (search + category)
        $filteredTotal = $query->count();

        return view('ai-plus.agent-templates.index', [
            'templates' => collect($templates),
            'templatesPaginator' => $templatesPaginator,
            'categories' => $categories,
            'categoryLabels' => $categoryLabels,
            'viewingAs' => 'Teacher / Staff',
            'totalTemplates' => AgentTemplate::count(),
            'filteredTotal' => $filteredTotal,
            'totalCategories' => count($categories),
        ]);
    }

    public function useTemplate(Request $request, AgentTemplate $template)
    {
        $user = $request->user();
        $template->load('sourceAgent');

        $sourceAgent = $template->sourceAgent;
        abort_if($sourceAgent, 404);
        $agent = $user->agents()->create([
            'title' => $template->name,
            'description' => $template->description,
            'system_prompt' => $sourceAgent?->system_prompt
                ?: 'You are '.$template->name.".\n\n".$template->description,
            'is_shared' => false,
        ]);

        $template->increment('uses_count');
        UsageLog::create([
            'user_id' => $user->id,
            'activity_title' => 'Used template: '.Str::limit($template->name, 80),
            'source' => 'template_used',
            'related_agent_template_id' => $template->id,
        ]);

        return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id])
            ->with('success', 'Agent added to your workspace. You can start chatting now.');
    }
}
