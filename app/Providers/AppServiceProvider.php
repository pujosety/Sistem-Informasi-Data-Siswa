<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;
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

        if ($this->unreadCache !== null) {
            return $this->unreadCache;
        }

        // Cached per request: the shell and several components ask for it.
        //
        // A missing badge is never worth an error page, and this runs from a
        // '*' view composer — so an unreachable database (exactly what a fresh
        // deployment or a wrong DB_CONNECTION looks like) would otherwise turn
        // a single query failure into a 500 on every page, including errors.
        try {
            return $this->unreadCache = auth()->user()->unreadNotifications()->count();
        } catch (\Throwable $e) {
            report($e);

            return $this->unreadCache = 0;
        }
    }

    public function register(): void
    {
        //
    }

    /**
     * Branding with no database involved.
     *
     * Used by the error renderer and by the branding composer when settings
     * cannot be read. Values come from the DEFAULTS constant, never from the
     * database, so an error page can always render.
     *
     * @return array<string, mixed>
     */
    private function fallbackBrand(): array
    {
        $defaults = SettingsService::DEFAULTS;

        return [
            'name' => $defaults['app.name'][0],
            'shortName' => $defaults['app.short_name'][0],
            'tagline' => $defaults['app.tagline'][0],
            'logo' => null,
            'icon' => null,
            'primary' => $defaults['branding.primary_color'][0],
            'accent' => $defaults['branding.accent_color'][0],
            'school' => [
                'name' => $defaults['school.name'][0],
                'npsn' => $defaults['school.npsn'][0],
                'address' => $defaults['school.address'][0],
                'city' => $defaults['school.city'][0],
                'headmaster' => $defaults['school.headmaster'][0],
            ],
        ];
    }

    /**
     * Workspaces for the switcher, or an empty list when they cannot be read.
     *
     * WorkspaceService consults roles and assignments, so it is as
     * database-dependent as NavigationService.
     *
     * @return array<int, array<string, mixed>>
     */
    private function safeWorkspaces($user): array
    {
        try {
            return app(\App\Services\WorkspaceService::class)->forUser($user);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public function boot(): void
    {
        // Academic policies: authorization is permission AND classroom scope.
        foreach ([
            \App\Models\SchoolClass::class => \App\Policies\ClassroomPolicy::class,
            \App\Models\Enrollment::class => \App\Policies\EnrollmentPolicy::class,
            \App\Models\Document::class => \App\Policies\DocumentPolicy::class,
            \App\Models\Employee::class => \App\Policies\EmployeePolicy::class,
            \App\Models\Post::class => \App\Policies\PostPolicy::class,
            // Media is a shared resource, so removing from it is gated on
            // cms.media.manage and not on cms.posts.edit. See MediaPolicy.
            \App\Models\Media::class => \App\Policies\MediaPolicy::class,

            // Grade entry and guardian linking are both permission AND scope,
            // so they follow the same composition as the academic policies.
            //
            // They were missing here, which is why GuardianPolicy::view() ran
            // and answered false for an admin who demonstrably held both
            // guardian.view and classroom.view.all: with no policy registered
            // for Student, `authorize('view', [GuardianPolicy::class, $s])`
            // never reaches GuardianPolicy at all, and the class name is treated
            // as a plain argument rather than a policy to resolve.
            \App\Models\Grade::class => \App\Policies\GradePolicy::class,
            \App\Models\Student::class => \App\Policies\GuardianPolicy::class,
            \App\Models\Module::class => \App\Policies\ModulePolicy::class,
            \App\Models\Alumni::class => \App\Policies\AlumniPolicy::class,
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
            $user = auth()->user();

            // Navigation reads roles/permissions/assignments. On an
            // unmigrated or unreachable database it throws, and a shell that
            // cannot build a menu must still render (the installer and the
            // error pages both use the shell).
            try {
                $nav = app(NavigationService::class)->forUser($user);
            } catch (\Throwable $e) {
                report($e);
                $nav = ['items' => [], 'dock' => [], 'more' => []];
            }

            $view->with('navigation', $nav['items'])
                ->with('dockItems', $nav['dock'])
                ->with('moreItems', $nav['more'])
                ->with('workspaces', $this->safeWorkspaces($user))
                ->with('unreadNotifications', $this->unreadCount());
        });

        // Components rendered from a page (topbar, badges) do NOT inherit the
        // data a view composer passes to the parent view.
        View::composer('*', function ($view) {
            $view->with('unreadNotifications', $this->unreadCount());
        });

        /*
         | Branding is global and reaches EVERY view — including the 403/404/500
         | error templates. That made the error renderer itself depend on the
         | database: a DB failure raised a second failure while rendering the
         | first one, and the operator saw nothing useful.
         |
         | SettingsService now degrades to its DEFAULTS when the table is
         | missing or the connection is wrong, so this stays safe. The extra
         | try/catch is a backstop for any OTHER settings failure, and it
         | still renders an error page rather than a blank screen.
         */
        View::composer('*', function ($view) {
            try {
                $settings = app(SettingsService::class);
            } catch (\Throwable $e) {
                report($e);
                $view->with('brand', $this->fallbackBrand());

                return;
            }

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

        /*
         | Deliberately NOT forcing a root URL.
         |
         | URL::forceRootUrl() overrides the host Laravel detected from the
         | request. Behind a platform proxy that detection is CORRECT — it reads
         | X-Forwarded-Host/Proto. Overriding it with config('app.url') meant a
         | missing or default APP_URL (http://localhost) rewrote every generated
         | link: url(), route(), asset() and the storage disk URL all pointed at
         | localhost while the request itself was served from the real domain.
         |
         | That produced the reported symptom: a working page whose buttons
         | navigated to http://localhost. Clearing the forced root restores
         | host-relative generation, which is correct in every environment.
         |
         | APP_URL is still honoured, but only as the fallback Laravel itself
         | uses when a request carries no host at all (console, queue workers).
         | To pin the domain in production, set APP_URL in the environment.
         */
        // Intentionally left unset — see the note above.
    }
}
