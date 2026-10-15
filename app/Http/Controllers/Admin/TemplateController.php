<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AgentTemplate;
use App\Models\AgentTemplateFeature;
use App\Support\AiPlusCatalogCategories;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTemplate::with('features');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('group')) {
            $query->where('category_group', $request->string('group')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $templates = $query->paginate(15)->withQueryString();
        $categories = AiPlusCatalogCategories::allCategories();
        $categoryGroups = AiPlusCatalogCategories::GROUPS;
        $statuses = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

        return view('admin.templates.index', compact('templates', 'categories', 'categoryGroups', 'statuses'));
    }

    public function create()
    {
        $categories = AiPlusCatalogCategories::CATEGORIES;
        $categoryGroups = AiPlusCatalogCategories::GROUPS;

        return view('admin.templates.create', compact('categories', 'categoryGroups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'system_prompt' => 'required|string|min:100',
            'category_group' => 'required|in:teaching,school_operations',
            'category' => 'required|in:lesson_planning,instruction_differentiation,assessment_feedback,subject_specific,student_support,communication,administration_operations,events_planning,data_reporting',
            'status' => 'required|in:draft,published,archived',
            'features' => 'array',
            'features.*.title' => 'required|string',
            'features.*.description' => 'nullable|string',
            'features.*.icon' => 'nullable|string',
        ]);

        if (! AiPlusCatalogCategories::contains($data['category_group'], $data['category'])) {
            return back()->withErrors(['category' => 'Choose a category that belongs to the selected group.'])->withInput();
        }

        $template = AgentTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'system_prompt' => $data['system_prompt'],
            'category_group' => $data['category_group'],
            'category' => $data['category'],
            'status' => $data['status'],
        ]);

        foreach ($data['features'] ?? [] as $feat) {
            AgentTemplateFeature::create([
                'agent_template_id' => $template->id,
                'title' => $feat['title'],
                'description' => $feat['description'] ?? '',
                'icon' => $feat['icon'] ?? '',
            ]);
        }
        AdminAuditLog::record('template.created', $template, ['status' => $template->status]);

        return redirect()->route('admin.templates.index')->with('success', 'Template created successfully.');
    }

    public function edit(AgentTemplate $template)
    {
        $categories = AiPlusCatalogCategories::CATEGORIES;
        $categoryGroups = AiPlusCatalogCategories::GROUPS;
        $template->load('features');

        return view('admin.templates.edit', compact('template', 'categories', 'categoryGroups'));
    }

    public function update(Request $request, AgentTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'system_prompt' => 'required|string|min:100',
            'category_group' => 'required|in:teaching,school_operations',
            'category' => 'required|in:lesson_planning,instruction_differentiation,assessment_feedback,subject_specific,student_support,communication,administration_operations,events_planning,data_reporting',
            'status' => 'required|in:draft,published,archived',
            'features' => 'array',
            'features.*.id' => 'nullable|integer',
            'features.*.title' => 'required|string',
            'features.*.description' => 'nullable|string',
            'features.*.icon' => 'nullable|string',
            'features.*._delete' => 'boolean',
        ]);

        if (! AiPlusCatalogCategories::contains($data['category_group'], $data['category'])) {
            return back()->withErrors(['category' => 'Choose a category that belongs to the selected group.'])->withInput();
        }

        $template->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'system_prompt' => $data['system_prompt'],
            'category_group' => $data['category_group'],
            'category' => $data['category'],
            'status' => $data['status'],
        ]);

        // Handle features
        foreach ($data['features'] ?? [] as $feat) {
            if ($feat['_delete'] ?? false) {
                if ($feat['id'] ?? null) {
                    $template->features()->whereKey($feat['id'])->delete();
                }

                continue;
            }
            if ($feat['id'] ?? null) {
                $template->features()->whereKey($feat['id'])->update([
                    'title' => $feat['title'],
                    'description' => $feat['description'] ?? '',
                    'icon' => $feat['icon'] ?? '',
                ]);
            } else {
                AgentTemplateFeature::create([
                    'agent_template_id' => $template->id,
                    'title' => $feat['title'],
                    'description' => $feat['description'] ?? '',
                    'icon' => $feat['icon'] ?? '',
                ]);
            }
        }
        AdminAuditLog::record('template.updated', $template, ['status' => $template->status]);

        return redirect()->route('admin.templates.index')->with('success', 'Template updated successfully.');
    }

    public function destroy(AgentTemplate $template)
    {
        AdminAuditLog::record('template.deleted', $template, ['name' => $template->name]);
        $template->delete();

        return redirect()->route('admin.templates.index')->with('success', 'Template deleted successfully.');
    }
}
