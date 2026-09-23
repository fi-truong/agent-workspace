<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AiPolicyAcceptanceController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiPolicyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        // Users must be able to read the full policy before they accept it.
        if ($request->routeIs('ai-plus.ai-policy.index')) {
            return $next($request);
        }

        if (AiPolicyAcceptanceController::hasAcceptedCurrentVersion($request->user())) {
            return $next($request);
        }

        $url = route('ai-plus.policy-acceptance.show');

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Accept the AI+ policy before continuing.',
                'requires_policy_acceptance' => true,
                'policy_url' => $url,
            ], 403);
        }

        return redirect()->guest($url);
    }
}
