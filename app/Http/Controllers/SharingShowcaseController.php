<?php

namespace App\Http\Controllers;

use App\Models\ShowcasePost;
use Illuminate\Http\Request;

class SharingShowcaseController extends Controller
{
    public function index()
    {
        $showcases = ShowcasePost::with(['author', 'tags'])->latest()->get()->map(function ($post) {
            return [
                'id' => $post->id,
                'author' => $post->author->name,
                'authorInitials' => $post->author->initials,
                'department' => $post->department,
                'title' => $post->title,
                'description' => $post->description,
                'tags' => $post->tags->pluck('name')->toArray(),
                'views' => $post->views_count,
                'comments' => $post->comments_count,
                'uses' => $post->uses_count,
                'badge' => $post->badge,
            ];
        })->toArray();

        return view('ai-plus.sharing-showcase.index', [
            'showcases' => $showcases,
            'viewingAs' => 'Teacher / Staff',
            'totalAgents' => ShowcasePost::count(),
            'totalDepartments' => ShowcasePost::distinct('department')->count('department'),
            'totalComments' => ShowcasePost::sum('comments_count'),
        ]);
    }
}
