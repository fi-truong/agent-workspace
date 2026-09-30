<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAiPolicyAccepted;
use App\Http\Middleware\GuardrailMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventAuthenticatedPageCache;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
            EnsureActiveAccount::class,
            GuardrailMiddleware::class,
            PreventAuthenticatedPageCache::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'ai.policy' => EnsureAiPolicyAccepted::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login.local.form'));

        // ngrok forwards requests to this local PHP server over loopback HTTP.
        // Trust its forwarding headers so Laravel retains the original public
        // HTTPS scheme when generating asset and route URLs. This keeps both
        // the local URL and the ngrok URL usable without a fixed ASSET_URL.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please sign in again.',
                    'session_expired' => true,
                ], 419);
            }

            return redirect()
                ->route('login.local.form')
                ->with('status', 'Your session has expired. Please sign in again.');
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
