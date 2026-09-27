<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private ?int $unreadCache = null;

    private function unreadCount(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        // Cached per request: the shell and several components ask for it.
        return $this->unreadCache ??= auth()->user()->unreadNotifications()->count();
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Academic policies: authorization is permission AND classroom scope.
        foreach ([
            \App\Models\SchoolClass::class => \App\Policies\ClassroomPolicy::class,
            \App\Models\Enrollment::class => \App\Policies\EnrollmentPolicy::class,
        ] as $model => $policy) {
            Gate::policy($model, $policy);
        }

        /*
         | Authorization is centralized in User::can(). The same two hard rules
         | are registered as a Gate::before so that Gate::allows() / @can / the
         | `can:` middleware resolve identically to $user->can().
         |
         | Spatie also registers a Gate::before during the provider register()
         | phase. Laravel runs every before callback in order and ANY one
         | returning a definitive answer wins, so this callback must return
         | null (not false) when the answer is merely "no permission" — that
         | defers to the normal ability lookup instead of short-circuiting.
         */
        Gate::before(function (User $user, string $ability, array $args = []) {
            // A disabled account is denied everything, even Super Admin.
            if (! $user->isActive()) {
                return false;
            }

            // Super Admin passes everything, including permissions added later.
            return $user->isSuperAdmin() ? true : null;
        });

        // Navigation + unread badge are needed by the shell on every page.
        View::composer('components.app-shell', function ($view) {
            $nav = app(NavigationService::class)->forUser(auth()->user());

            $view->with('navigation', $nav['items'])
                ->with('dockItems', $nav['dock'])
                ->with('unreadNotifications', $this->unreadCount());
        });

        // Components rendered from a page (topbar, badges) do NOT inherit the
        // data a view composer passes to the parent view.
        View::composer('*', function ($view) {
            $view->with('unreadNotifications', $this->unreadCount());
        });

        // Branding is global: expose it to every view, including components.
        View::composer('*', function ($view) {
            $view->with('brand', [
                'name' => app(SettingsService::class)->get('app.name'),
                'shortName' => app(SettingsService::class)->get('app.short_name'),
                'tagline' => app(SettingsService::class)->get('app.tagline'),
                'logo' => app(SettingsService::class)->asset('branding.logo'),
                'icon' => app(SettingsService::class)->asset('branding.icon'),
                'primary' => app(SettingsService::class)->get('branding.primary_color'),
                'accent' => app(SettingsService::class)->get('branding.accent_color'),
                'school' => [
                    'name' => app(SettingsService::class)->get('school.name'),
                    'npsn' => app(SettingsService::class)->get('school.npsn'),
                    'address' => app(SettingsService::class)->get('school.address'),
                    'city' => app(SettingsService::class)->get('school.city'),
                    'headmaster' => app(SettingsService::class)->get('school.headmaster'),
                ],
            ]);
        });

        // Force generated URLs to match the configured APP_URL behind a proxy.
        if ($url = config('app.url')) {
            URL::forceRootUrl($url);
        }
    }
}
