<?php

namespace App\Services\AI;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CompanionContextService
{
    public function forRequest(Request $request, School $school): array
    {
        $routeName = $request->route()?->getName();
        $user = $request->user();
        $roles = $user && method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()->all()
            : [];

        return [
            'audience' => $user ? 'authenticated' : 'public',
            'module' => $this->module($routeName, $request->path()),
            'route' => $routeName,
            'roles' => $roles,
            'school' => array_filter([
                'name' => $school->name,
                'slug' => $school->slug,
            ], static fn ($value) => filled($value)),
        ];
    }

    private function module(?string $routeName, string $path): string
    {
        $route = Str::lower((string) $routeName);
        $path = Str::lower($path);

        return match (true) {
            Str::startsWith($route, 'lms.') || Str::startsWith($path, 'lms/') => 'lms',
            Str::contains($route.' '.$path, ['ppdb', 'registration', 'registrasi']) => 'admissions',
            Str::startsWith($route, 'cms.') || Str::startsWith($route, 'landing.') => 'cms',
            Str::contains($route.' '.$path, ['analytics', 'laporan']) => 'analytics',
            Str::startsWith($route, 'public.') || $route === 'home' || $path === '/' => 'public',
            $route !== '' => 'account',
            default => 'general',
        };
    }
}
