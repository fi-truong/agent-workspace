<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function index(Request $request)
    {
        $query = PromptLibraryPrompt::with('tags');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('preview_text', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->filled('subject')) {
            $query->whereHas('tags', fn($q) => $q->where('category', 'subject')->where('name', $request->subject));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match($sort) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $prompts = $query->paginate(15)->withQueryString();

        $subjects = Tag::where('category', 'subject')->pluck('name')->all();
        $statuses = ['draft' => 'Draft', 'published' => 'Published'];

        return view('admin.prompts.index', compact('prompts', 'subjects', 'statuses'));
    }

    public function create()
    {
        $subjects = Tag::where('category', 'subject')->pluck('name', 'name')->all();
        return view('admin.prompts.create', compact('subjects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_text' => 'required|string',
            'subject' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        $prompt = PromptLibraryPrompt::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'preview_text' => $data['preview_text'],
            'status' => $data['status'],
        ]);

        // Attach subject tag
        $tag = Tag::firstOrCreate(['name' => $data['subject'], 'category' => 'subject']);
        $prompt->tags()->attach($tag->id);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt created successfully.');
    }

    public function show(PromptLibraryPrompt $prompt)
    {
        return view('admin.prompts.show', compact('prompt'));
    }

    public function edit(PromptLibraryPrompt $prompt)
    {
        $subjects = Tag::where('category', 'subject')->pluck('name', 'name')->all();
        $currentSubject = $prompt->tags()->where('category', 'subject')->value('name');
        return view('admin.prompts.edit', compact('prompt', 'subjects', 'currentSubject'));
    }

    public function update(Request $request, PromptLibraryPrompt $prompt)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_text' => 'required|string',
            'subject' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        $prompt->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'preview_text' => $data['preview_text'],
            'status' => $data['status'],
        ]);

        // Update subject tag
        $prompt->tags()->wherePivotIn('tag_id', Tag::where('category', 'subject')->pluck('id'))->detach();
        $tag = Tag::firstOrCreate(['name' => $data['subject'], 'category' => 'subject']);
        $prompt->tags()->attach($tag->id);

        return redirect()->route('admin.prompts.index')->with('success', 'Prompt updated successfully.');
    }

    public function destroy(PromptLibraryPrompt $prompt)
    {
        $prompt->delete();
        return redirect()->route('admin.prompts.index')->with('success', 'Prompt deleted successfully.');
    }
}