<?php

namespace App\Http\Controllers;

use App\Models\AgentTemplate;
use App\Models\UsageLog;
use App\Models\User;
use App\Support\AiPlusCatalogCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTemplate::with('features')
            ->whereNull('source_agent_id')
            ->where('status', 'published')
            ->latest();

        if ($request->filled('group')) {
            $query->where('category_group', $request->string('group')->toString());
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('system_prompt', 'like', "%{$search}%");
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

        // Keep enough templates together to avoid a mostly empty final grid row.
        $templatesPaginator = $query->paginate(12)->withQueryString();

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
                'category_group' => $t->category_group,
                'is_shared_agent' => $t->source_agent_id !== null,
            ];
        })->toArray();

        // Categories for filter buttons (from DB)
        $categories = AiPlusCatalogCategories::CATEGORIES;

        // Category display names
        $categoryLabels = AiPlusCatalogCategories::allCategories();

        // Total count for current filter (search + category)
        $filteredTotal = (clone $query)->count();

        return view('ai-plus.agent-templates.index', [
            'templates' => collect($templates),
            'templatesPaginator' => $templatesPaginator,
            'categories' => $categories,
            'categoryLabels' => $categoryLabels,
            'categoryGroups' => AiPlusCatalogCategories::GROUPS,
            'catalogCategories' => $categories,
            'viewingAs' => 'Teacher / Staff',
            'totalTemplates' => AgentTemplate::where('status', 'published')->whereNull('source_agent_id')->count(),
            'filteredTotal' => $filteredTotal,
            'totalCategories' => count(AiPlusCatalogCategories::allCategories()),
        ]);
    }

    public function useTemplate(Request $request, AgentTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $template->load('sourceAgent');

        abort_if($template->status !== 'published', 404);

        abort_if($template->source_agent_id !== null, 404);
        $agent = $user->agents()->create([
            'title' => $template->name,
            'description' => $template->description,
            'system_prompt' => $template->system_prompt ?: 'You are '.$template->name.".\n\n".$template->description,
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
