<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index()
    {
        $faqs = Faq::orderBy('sort_order')->get()->map(function ($faq) {
            return [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ];
        })->toArray();

        return view('ai-plus.support.index', [
            'faqs' => $faqs,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }
}
