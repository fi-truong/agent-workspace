<?php

namespace App\Http\Controllers;

use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use Illuminate\Http\Request;

class PromptLibraryController extends Controller
{
    public function index(Request $request)
    {
        $query = PromptLibraryPrompt::with(['author', 'tags'])->latest();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('preview_text', 'like', "%{$search}%");
            });
        }

        // Subject filter
        if ($request->filled('subject')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('name', $request->subject);
            });
        }

        // Sort
        $sort = $request->get('sort', 'popular');
        if ($sort === 'popular') {
            $query->orderByDesc('uses_count');
        } elseif ($sort === 'new') {
            $query->latest();
        }

        $prompts = $query->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'icon' => 'PL',
                'title' => $p->title,
                'description' => $p->description,
                'preview' => $p->preview_text,
                'author' => $p->author->name,
                'authorInitials' => $p->author->initials,
                'badges' => $p->tags->pluck('name')->toArray(),
                'uses' => $p->uses_count,
            ];
        })->toArray();

        // Subjects for filter buttons (only 'subject' category tags)
        $subjects = Tag::where('category', 'subject')->orderBy('name')->get();

        return view('ai-plus.prompt-library.index', [
            'prompts' => collect($prompts),
            'subjects' => $subjects,
            'viewingAs' => 'Teacher / Staff',
            'totalPrompts' => PromptLibraryPrompt::count(),
            'totalSubjects' => Tag::where('category', 'subject')->count(),
        ]);
    }
}