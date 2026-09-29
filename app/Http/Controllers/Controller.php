<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * The base every controller in this application extends.
 *
 * WHY IT NOW EXTENDS THE FRAMEWORK'S
 *
 * This class was empty. That is why every policy check in the codebase was
 * written by hand — a controller had no $this->authorize(), so the ownership
 * test in DocumentFileController was thirty lines of inline PHP, and
 * ClassroomPolicy and EnrollmentPolicy were only ever reached through Gate.
 *
 * An empty base is a workable choice when no controller needs framework
 * helpers, but it means $this->authorize(), $this->validate() and
 * authorizeResource() are all unavailable, so a policy can only be consulted by
 * resolving it manually. The first caller who forgets inherits a 403-or-nothing
 * decision rather than a framework-enforced one.
 *
 * Extending the framework base costs nothing and makes the policy the default
 * path instead of the exceptional one. Nothing existing changes: these traits
 * are the framework's own, so the behaviour of a controller that already
 * resolves its own policies is identical.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
