<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleService $modules)
    {
    }

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->isEnabled($module), 404);

        return $next($request);
    }
}
