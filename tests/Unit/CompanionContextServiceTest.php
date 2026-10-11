<?php

namespace Tests\Unit;

use App\Models\School;
use App\Services\AI\CompanionContextService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CompanionContextServiceTest extends TestCase
{
    public function test_it_builds_minimal_role_and_module_context_without_route_identifiers(): void
    {
        $request = Request::create('/lms/courses/42', 'GET');
        $route = new Route('GET', 'lms/courses/{course}', []);
        $route->name('lms.student.courses.show');
        $request->setRouteResolver(fn () => $route);

        $context = (new CompanionContextService())->forRequest(
            $request,
            new School(['id' => 7, 'name' => 'SMP Negeri 4 Metro', 'slug' => 'smp-negeri-4-metro'])
        );

        $this->assertSame('lms', $context['module']);
        $this->assertSame('lms.student.courses.show', $context['route']);
        $this->assertSame(['name' => 'SMP Negeri 4 Metro', 'slug' => 'smp-negeri-4-metro'], $context['school']);
        $this->assertArrayNotHasKey('course_id', $context);
        $this->assertArrayNotHasKey('user_id', $context);
    }

    public function test_public_requests_are_marked_as_public_without_user_data(): void
    {
        $request = Request::create('/', 'GET');
        $route = new Route('GET', '/', []);
        $route->name('public.home');
        $request->setRouteResolver(fn () => $route);

        $context = (new CompanionContextService())->forRequest(
            $request,
            new School(['id' => 7, 'name' => 'SMP Negeri 4 Metro', 'slug' => 'smp-negeri-4-metro'])
        );

        $this->assertSame('public', $context['module']);
        $this->assertSame('public', $context['audience']);
        $this->assertSame([], $context['roles']);
    }
}
