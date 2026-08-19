<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AiPolicyController extends Controller
{
    public function index()
    {
        return view('ai-plus.ai-policy.index', [
            'viewingAs' => 'Teacher / Staff',
            'lastUpdated' => 'August 19, 2026',
            'version' => '1.0',
        ]);
    }
}
