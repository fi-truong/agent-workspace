<?php

namespace App\Http\Controllers;

use App\Models\AgentTemplate;
use Illuminate\Http\Request;

class AgentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTemplate::with('features')->latest();

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
}