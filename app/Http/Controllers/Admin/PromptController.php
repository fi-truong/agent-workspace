<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use App\Support\AiPlusCatalogCategories;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function index(Request $request)
    {
        $query = PromptLibraryPrompt::with('tags');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('preview_text', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->filled('subject')) {
            $query->whereHas('tags', fn ($q) => $q->where('category', 'subject')->where('name', $request->subject));
        }
        if ($request->filled('group')) {
            $query->where('category_group', $request->string('group')->toString());
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $prompts = $query->paginate(15)->withQueryString();

        $subjects = Tag::where('category', 'subject')->pluck('name')->all();
        $statuses = ['draft' => 'Draft', 'published' => 'Published'];
        $categoryGroups = AiPlusCatalogCategories::GROUPS;
        $categories = AiPlusCatalogCategories::allCategories();

        return view('admin.prompts.index', compact('prompts', 'subjects', 'statuses', 'categoryGroups', 'categories'));
    }

    public function create()
    {
        $subjects = Tag::where('category', 'subject')->pluck('name', 'name')->all();

        $categoryGroups = AiPlusCatalogCategories::GROUPS;
        $catalogCategories = AiPlusCatalogCategories::CATEGORIES;

        return view('admin.prompts.create', compact('subjects', 'categoryGroups', 'catalogCategories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_text' => 'required|string',
            'subject' => 'required|string',
            'status' => 'required|in:draft,published',
            'category_group' => 'required|in:teaching,school_operations',
            'category' => 'required|in:lesson_planning,instruction_differentiation,assessment_feedback,subject_specific,student_support,communication,administration_operations,events_planning,data_reporting',
        ]);

        if (! AiPlusCatalogCategories::contains($data['category_group'], $data['category'])) {
            return back()->withErrors(['category' => 'Choose a category that belongs to the selected group.'])->withInput();
        }

        $prompt = PromptLibraryPrompt::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'preview_text' => $data['preview_text'],
            'status' => $data['status'],
            'category_group' => $data['category_group'],
            'category' => $data['category'],
        ]);

        // Attach subject tag
        $tag = Tag::firstOrCreate(['name' => $data['subject'], 'category' => 'subject']);
        $prompt->tags()->attach($tag->id);
        AdminAuditLog::record('prompt.created', $prompt, ['status' => $prompt->status]);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt created successfully.');
    }

    public function edit(PromptLibraryPrompt $prompt)
    {
        $subjects = Tag::where('category', 'subject')->pluck('name', 'name')->all();
        $currentSubject = $prompt->tags()->where('category', 'subject')->value('name');
        $categoryGroups = AiPlusCatalogCategories::GROUPS;
        $catalogCategories = AiPlusCatalogCategories::CATEGORIES;

        return view('admin.prompts.edit', compact('prompt', 'subjects', 'currentSubject', 'categoryGroups', 'catalogCategories'));
    }

    public function update(Request $request, PromptLibraryPrompt $prompt)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_text' => 'required|string',
            'subject' => 'required|string',
            'status' => 'required|in:draft,published',
            'category_group' => 'required|in:teaching,school_operations',
            'category' => 'required|in:lesson_planning,instruction_differentiation,assessment_feedback,subject_specific,student_support,communication,administration_operations,events_planning,data_reporting',
        ]);

        if (! AiPlusCatalogCategories::contains($data['category_group'], $data['category'])) {
            return back()->withErrors(['category' => 'Choose a category that belongs to the selected group.'])->withInput();
        }

        $prompt->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'preview_text' => $data['preview_text'],
            'status' => $data['status'],
            'category_group' => $data['category_group'],
            'category' => $data['category'],
        ]);

        // Update subject tag
        $prompt->tags()->wherePivotIn('tag_id', Tag::where('category', 'subject')->pluck('id'))->detach();
        $tag = Tag::firstOrCreate(['name' => $data['subject'], 'category' => 'subject']);
        $prompt->tags()->attach($tag->id);
        AdminAuditLog::record('prompt.updated', $prompt, ['status' => $prompt->status]);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt updated successfully.');
    }

    public function destroy(PromptLibraryPrompt $prompt)
    {
        AdminAuditLog::record('prompt.deleted', $prompt, ['title' => $prompt->title]);
        $prompt->delete();

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt deleted successfully.');
    }
}
