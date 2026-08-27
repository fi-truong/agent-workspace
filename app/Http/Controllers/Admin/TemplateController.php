<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentTemplate;
use App\Models\AgentTemplateFeature;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentTemplate::with('features');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'newest');
        match($sort) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $templates = $query->paginate(15)->withQueryString();
        $categories = AgentTemplate::distinct()->pluck('category')->filter()->all();
        $statuses = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

        return view('admin.templates.index', compact('templates', 'categories', 'statuses'));
    }

    public function create()
    {
        $categories = AgentTemplate::distinct()->pluck('category')->filter()->all();
        return view('admin.templates.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:100',
            'status' => 'required|in:draft,published,archived',
            'features' => 'array',
            'features.*.title' => 'required|string',
            'features.*.description' => 'nullable|string',
            'features.*.icon' => 'nullable|string',
        ]);

        $template = AgentTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'],
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

        return redirect()->route('admin.templates.index')->with('success', 'Template created successfully.');
    }

    public function show(AgentTemplate $template)
    {
        $template->load('features');
        return view('admin.templates.show', compact('template'));
    }

    public function edit(AgentTemplate $template)
    {
        $categories = AgentTemplate::distinct()->pluck('category')->filter()->all();
        $template->load('features');
        return view('admin.templates.edit', compact('template', 'categories'));
    }

    public function update(Request $request, AgentTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:100',
            'status' => 'required|in:draft,published,archived',
            'features' => 'array',
            'features.*.id' => 'nullable|integer',
            'features.*.title' => 'required|string',
            'features.*.description' => 'nullable|string',
            'features.*.icon' => 'nullable|string',
            'features.*._delete' => 'boolean',
        ]);

        $template->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'category' => $data['category'],
            'status' => $data['status'],
        ]);

        // Handle features
        foreach ($data['features'] ?? [] as $feat) {
            if ($feat['_delete'] ?? false) {
                if ($feat['id']) AgentTemplateFeature::find($feat['id'])?->delete();
                continue;
            }
            if ($feat['id']) {
                AgentTemplateFeature::find($feat['id'])?->update([
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

        return redirect()->route('admin.templates.index')->with('success', 'Template updated successfully.');
    }

    public function destroy(AgentTemplate $template)
    {
        $template->delete();
        return redirect()->route('admin.templates.index')->with('success', 'Template deleted successfully.');
    }
}