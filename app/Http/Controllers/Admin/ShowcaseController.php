<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ShowcasePost;
use Illuminate\Http\Request;

class ShowcaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ShowcasePost::with('tags', 'author');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'popular' => $query->orderByDesc('views_count'),
            'alpha' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $showcases = $query->paginate(15)->withQueryString();
        $statuses = ['pending' => 'Pending review', 'draft' => 'Draft', 'published' => 'Published', 'rejected' => 'Rejected'];

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
            'description' => 'required|string',
            'department' => 'nullable|string|max:100',
            'status' => 'required|in:draft,pending,published,rejected',
            'author_id' => 'nullable|exists:users,id',
        ]);

        $data['author_id'] = $data['author_id'] ?? auth()->id();
        $data['department'] = $data['department'] ?? auth()->user()?->department ?? 'General';
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        $showcase = ShowcasePost::create($data);
        AdminAuditLog::record('showcase.created', $showcase, ['status' => $showcase->status]);

        return redirect()->route('admin.showcases.index')->with('success', 'Showcase created successfully.');
    }

    public function edit(ShowcasePost $showcase)
    {
        return view('admin.showcases.edit', compact('showcase'));
    }

    public function update(Request $request, ShowcasePost $showcase)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'department' => 'nullable|string|max:100',
            'status' => 'required|in:draft,pending,published,rejected',
        ]);

        $data['published_at'] = $data['status'] === 'published'
            ? ($showcase->published_at ?? now())
            : null;

        $showcase->update($data);
        AdminAuditLog::record('showcase.updated', $showcase, ['status' => $showcase->status]);

        return redirect()->route('admin.showcases.index')->with('success', 'Showcase updated successfully.');
    }

    public function destroy(ShowcasePost $showcase)
    {
        AdminAuditLog::record('showcase.deleted', $showcase, ['title' => $showcase->title]);
        $showcase->delete();

        return redirect()->route('admin.showcases.index')->with('success', 'Showcase deleted successfully.');
    }
}
