<?php

namespace App\Http\Controllers;

use App\Models\ShowcasePost;
use App\Models\ShowcaseComment;
use App\Models\ShowcaseUse;
use App\Models\ShowcaseView;
use App\Services\KnowledgeService;
use App\Services\PiiFilterService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SharingShowcaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ShowcasePost::with(['author', 'tags'])
            ->withCount('comments')
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

        // View tabs use live activity data, never the legacy badge cache.
        $view = $request->get('view', 'all');
        switch ($view) {
            case 'trending':
                $query->orderByDesc('views_count')->orderByDesc('uses_count');
                break;
            case 'new':
                $query->where('published_at', '>=', now()->subDays(7))->orderByDesc('published_at');
                break;
            case 'mostused':
                $query->orderByDesc('uses_count')->orderByDesc('views_count');
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
                'badge' => $this->badgeFor($post),
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
            'totalComments' => ShowcaseComment::whereHas('showcase', fn ($query) => $query->where('status', 'published'))->count(),
        ]);
    }

    public function show(Request $request, ShowcasePost $showcase)
    {
        // Only published posts are viewable on the public side
        abort_if($showcase->status !== 'published', 404);

        // Count one view per signed-in user per Showcase per calendar day.
        $created = ShowcaseView::query()->insertOrIgnore([
            'showcase_post_id' => $showcase->id,
            'user_id' => $request->user()->id,
            'viewed_on' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($created === 1) {
            $showcase->increment('views_count');
        }

        $post = [
            'id' => $showcase->id,
            'author' => $showcase->author?->name ?? 'Unknown',
            'authorInitials' => $showcase->author?->initials ?? '?',
            'department' => $showcase->department,
            'title' => $showcase->title,
            'description' => $showcase->description,
            'tags' => $showcase->tags->pluck('name')->toArray(),
            'views' => $showcase->views_count,
            'comments' => $showcase->comments()->count(),
            'uses' => $showcase->uses_count,
            'badge' => $this->badgeFor($showcase),
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
            ->withCount('comments')
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
                    'badge' => $this->badgeFor($r),
                    'url' => route('ai-plus.sharing-showcase.show', $r->id),
                ];
            });

        $comments = $showcase->comments()
            ->with('user')
            ->oldest()
            ->get();

        return view('ai-plus.sharing-showcase.show', [
            'post' => $post,
            'related' => $related,
            'comments' => $comments,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }

    public function storeComment(Request $request, ShowcasePost $showcase, PiiFilterService $piiFilter)
    {
        abort_if($showcase->status !== 'published', 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if ($piiFilter->scan($data['content'])['flagged']) {
            return back()
                ->withErrors(['content' => 'Comments cannot contain sensitive personal information. Please remove it and try again.'])
                ->withInput()
                ->withFragment('comments');
        }

        $showcase->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);
        $showcase->update(['comments_count' => $showcase->comments()->count()]);

        return redirect()
            ->route('ai-plus.sharing-showcase.show', $showcase)
            ->with('success', 'Your comment has been posted.')
            ->withFragment('comments');
    }

    public function destroyComment(Request $request, ShowcasePost $showcase, ShowcaseComment $comment)
    {
        abort_if($comment->showcase_post_id !== $showcase->id, 404);
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->role === 'admin',
            403,
        );

        $comment->delete();
        $showcase->update(['comments_count' => $showcase->comments()->count()]);

        return redirect()
            ->route('ai-plus.sharing-showcase.show', $showcase)
            ->with('success', 'Comment deleted.')
            ->withFragment('comments');
    }

    public function use(Request $request, ShowcasePost $showcase, KnowledgeService $knowledgeService)
    {
        abort_if($showcase->status !== 'published', 404);
        $showcase->load('sourceAgent');

        if (! $showcase->sourceAgent?->is_shared) {
            return back()->withErrors(['showcase' => 'Showcase này chưa có agent để sử dụng.']);
        }

        $source = $showcase->sourceAgent;

        if ($source->user_id === $request->user()->id) {
            return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $source->id])
                ->with('success', 'This is your agent. Continue in Agent Workspace.');
        }

        if ($source->sharing_access !== 'copy') {
            $created = $this->recordUse($showcase, $request->user()->id);
            if ($created) {
                $showcase->increment('uses_count');
            }

            return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $source->id])
                ->with('success', $created
                    ? 'You can now use this shared agent.'
                    : 'You are already using this agent. Continue in Agent Workspace.');
        }

        $existingCopy = $request->user()->agents()
            ->where('copied_from_agent_id', $source->id)
            ->first();
        if ($existingCopy) {
            return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $existingCopy->id])
                ->with('success', 'You have already added this agent. Continue editing it in My Agents.');
        }

        try {
            $knowledgeService->assertSharedKnowledgeAvailable($source);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['showcase' => $exception->getMessage()]);
        }

        try {
            $agent = $request->user()->agents()->create([
                'copied_from_agent_id' => $source->id,
                'title' => $source->title,
                'description' => $source->description,
                'system_prompt' => $source->system_prompt,
                'is_shared' => false,
            ]);
        } catch (QueryException) {
            $existingCopy = $request->user()->agents()
                ->where('copied_from_agent_id', $source->id)
                ->firstOrFail();

            return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $existingCopy->id])
                ->with('success', 'You have already added this agent. Continue editing it in My Agents.');
        }
        try {
            $knowledgeService->copySharedKnowledge($source, $agent);
        } catch (\RuntimeException $exception) {
            $knowledgeService->deleteAgentKnowledge($agent->user_id, $agent->id);
            $agent->delete();

            return back()->withErrors(['showcase' => 'This agent could not be copied completely. Please try again or contact the owner.']);
        }
        if ($this->recordUse($showcase, $request->user()->id)) {
            $showcase->increment('uses_count');
        }

        return redirect()->route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id]);
    }

    private function badgeFor(ShowcasePost $post): ?string
    {
        if ($post->uses_count >= 20) {
            return '⭐ Popular';
        }

        return $post->published_at?->greaterThanOrEqualTo(now()->subDays(7)) ? 'New' : null;
    }

    private function recordUse(ShowcasePost $showcase, int $userId): bool
    {
        try {
            $use = ShowcaseUse::query()->firstOrCreate([
                'showcase_post_id' => $showcase->id,
                'user_id' => $userId,
            ]);
        } catch (QueryException) {
            // The unique index means another concurrent request recorded it first.
            return false;
        }

        return $use->wasRecentlyCreated;
    }
}
