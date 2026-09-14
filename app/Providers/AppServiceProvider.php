<?php

namespace App\Providers;

use App\Models\Agent;
use App\Policies\AgentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Agent::class, AgentPolicy::class);

        View::composer(['layouts.ai-plus', 'layouts.app'], function ($view) {
            $view->with('currentUser', auth()->user());
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', Provider::class);
        });

        // Use custom pagination view
        Paginator::defaultView('vendor.pagination.tailwind');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
