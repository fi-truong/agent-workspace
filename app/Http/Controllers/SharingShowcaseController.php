<?php

namespace App\Http\Controllers;

use App\Models\ShowcasePost;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SharingShowcaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ShowcasePost::with(['author', 'tags'])
            ->where('status', 'published');

        // Search (title, description, author name, department)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        // Department filter
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        // View tabs (combined badge filter + sort)
        $view = $request->get('view', 'all');
        $badgeVisible = true;
        switch ($view) {
            case 'trending':
                $query->where('badge', '🔥 Trending')->orderByDesc('views_count');
                break;
            case 'new':
                $query->where('badge', 'New')->orderByDesc('created_at');
                break;
            case 'mostused':
                $query->where('badge', '⭐ Popular')->orderByDesc('uses_count');
                break;
            case 'all':
            default:
                $query->latest();
                break;
        }

        // Pagination - 9 per page (3-col grid)
        $paginator = $query->paginate(9)->withQueryString();

        $showcases = $paginator->getCollection()->map(function ($post) {
            return [
                'id' => $post->id,
                'author' => $post->author?->name ?? 'Unknown',
                'authorInitials' => $post->author?->initials ?? '?',
                'department' => $post->department,
                'title' => $post->title,
                'description' => $post->description,
                'tags' => $post->tags->pluck('name')->toArray(),
                'views' => $post->views_count,
                'comments' => $post->comments_count,
                'uses' => $post->uses_count,
                'badge' => $post->badge,
                'url' => route('ai-plus.sharing-showcase.show', $post->id),
            ];
        });

        // Departments for filter chips
        $departments = ShowcasePost::select('department')->distinct()
            ->where('status', 'published')->whereNotNull('department')
            ->orderBy('department')->pluck('department')->toArray();

        return view('ai-plus.sharing-showcase.index', [
            'showcases' => collect($showcases),
            'paginator' => $paginator,
            'departments' => $departments,
            'view' => $view,
            'viewingAs' => 'Teacher / Staff',
            'totalAgents' => ShowcasePost::where('status', 'published')->count(),
            'totalDepartments' => count($departments),
            'totalComments' => ShowcasePost::where('status', 'published')->sum('comments_count'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:20|max:2000',
            'department' => 'required|string|max:100',
            'tags' => 'nullable|string|max:500',
            'source_type' => 'nullable|in:agent,workflow',
        ]);

        $user = Auth::user();
        $post = ShowcasePost::create([
            'author_id' => $user?->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'department' => $data['department'],
            'source_type' => $data['source_type'] ?? 'agent',
            'status' => 'pending', // Submissions go to admin review first
            'views_count' => 0,
            'comments_count' => 0,
            'uses_count' => 0,
        ]);

        // Attach tags (comma-separated input)
        if (!empty($data['tags'])) {
            $tagNames = array_filter(
                array_map('trim', explode(',', $data['tags']))
            );
            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName, 'category' => 'general']);
                $post->tags()->attach($tag->id);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Your showcase has been submitted for review. Thank you!',
                'id' => $post->id,
            ], 201);
        }

        return redirect()->route('ai-plus.sharing-showcase.index')
            ->with('flash', 'Your showcase has been submitted for review. Thank you!');
    }

    public function show(ShowcasePost $showcase)
    {
        // Only published posts are viewable on the public side
        abort_if($showcase->status !== 'published', 404);

        // Increment view counter
        $showcase->increment('views_count');

        $post = [
            'id' => $showcase->id,
            'author' => $showcase->author?->name ?? 'Unknown',
            'authorInitials' => $showcase->author?->initials ?? '?',
            'department' => $showcase->department,
            'title' => $showcase->title,
            'description' => $showcase->description,
            'tags' => $showcase->tags->pluck('name')->toArray(),
            'views' => $showcase->views_count,
            'comments' => $showcase->comments_count,
            'uses' => $showcase->uses_count,
            'badge' => $showcase->badge,
            'created' => $showcase->created_at?->format('d/m/Y'),
        ];

        // Related showcases (same department or shared tags), max 3
        $related = ShowcasePost::where('status', 'published')
            ->where('id', '!=', $showcase->id)
            ->where(function ($q) use ($showcase) {
                $q->where('department', $showcase->department)
                  ->orWhereHas('tags', function ($t) use ($showcase) {
                      $t->whereIn('tags.id', $showcase->tags->pluck('id'));
                  });
            })
            ->with('author', 'tags')
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'author' => $r->author?->name ?? 'Unknown',
                    'authorInitials' => $r->author?->initials ?? '?',
                    'department' => $r->department,
                    'title' => $r->title,
                    'description' => $r->description,
                    'views' => $r->views_count,
                    'comments' => $r->comments_count,
                    'uses' => $r->uses_count,
                    'badge' => $r->badge,
                    'url' => route('ai-plus.sharing-showcase.show', $r->id),
                ];
            });

        return view('ai-plus.sharing-showcase.show', [
            'post' => $post,
            'related' => $related,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }
}