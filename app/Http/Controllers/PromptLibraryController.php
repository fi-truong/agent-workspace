<?php

namespace App\Http\Controllers;

use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use Illuminate\Http\Request;

class PromptLibraryController extends Controller
{
    public function index()
    {
        $prompts = PromptLibraryPrompt::with(['author', 'tags'])->latest()->get()->map(function ($p) {
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

        return view('ai-plus.prompt-library.index', [
            'prompts' => $prompts,
            'viewingAs' => 'Teacher / Staff',
            'totalPrompts' => PromptLibraryPrompt::count(),
            'totalSubjects' => Tag::where('category', 'subject')->count(),
            'totalContributors' => PromptLibraryPrompt::distinct('author_id')->count('author_id'),
        ]);
    }
}
