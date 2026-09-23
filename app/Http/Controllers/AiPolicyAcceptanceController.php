<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiPolicyAcceptanceController extends Controller
{
    public const VERSION = '1.1';

    public function show(): View|RedirectResponse
    {
        $user = request()->user();

        if ($this->hasAcceptedCurrentVersion($user)) {
            return redirect()->route('ai-plus.index');
        }

        return view('ai-plus.policy-acceptance', ['version' => self::VERSION]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate([
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'You must accept the AI+ policy to continue.',
        ]);

        $request->user()->forceFill([
            'ai_policy_accepted_at' => now(),
            'ai_policy_version' => self::VERSION,
        ])->save();

        return redirect()->intended(route('ai-plus.index'));
    }

    public static function hasAcceptedCurrentVersion(mixed $user): bool
    {
        return $user !== null
            && $user->ai_policy_accepted_at !== null
            && $user->ai_policy_version === self::VERSION;
    }
}
