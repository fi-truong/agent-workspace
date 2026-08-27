<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShowcasePost;
use App\Models\Tag;
use Illuminate\Http\Request;

class ShowcaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ShowcasePost::with('tags', 'author');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'newest');
        match($sort) {
            'oldest' => $query->oldest(),
            'popular' => $query->orderByDesc('views_count'),
            'alpha' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $showcases = $query->paginate(15)->withQueryString();
        $statuses = ['draft' => 'Draft', 'published' => 'Published'];

        return view('admin.showcases.index', compact('showcases', 'statuses'));
    }

    public function create()
    {
        return view('admin.showcases.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
            'author_id' => 'nullable|exists:users,id',
        ]);

        $data['author_id'] = $data['author_id'] ?? auth()->id();
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        ShowcasePost::create($data);

        return redirect()->route('admin.showcases.index')->with('success', 'Showcase created successfully.');
    }

    public function show(ShowcasePost $showcase)
    {
        return view('admin.showcases.show', compact('showcase'));
    }

    public function edit(ShowcasePost $showcase)
    {
        return view('admin.showcases.edit', compact('showcase'));
    }

    public function update(Request $request, ShowcasePost $showcase)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        if ($showcase->status !== 'published' && $data['status'] === 'published') {
            $data['published_at'] = now();
        }

        $showcase->update($data);

        return redirect()->route('admin.showcases.index')->with('success', 'Showcase updated successfully.');
    }

    public function destroy(ShowcasePost $showcase)
    {
        $showcase->delete();
        return redirect()->route('admin.showcases.index')->with('success', 'Showcase deleted successfully.');
    }
}