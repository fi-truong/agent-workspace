<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        abort_if(! $team, 403);

        return $team;
    }

    /**
     * Fallback for SSO users without a team — redirect to AI+ homepage.
     */
    protected function redirectToIntendedOrAiPlus(Request $request): \Illuminate\Http\RedirectResponse
    {
        $team = $request->user()?->currentTeam ?? $request->user()?->personalTeam();

        if (! $team) {
            // User chưa có team → về trang AI+ (không cần team)
            return redirect()->route('ai-plus.index');
        }

        return redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
