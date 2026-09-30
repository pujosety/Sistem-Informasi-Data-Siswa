<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ADMIN → MODUL
 *
 * An operator currently has to run `php artisan sida:enable-modules` to switch
 * a section on. That is a shell as a product feature: the registry is meant to
 * be something a school administrator controls from the settings screen, and
 * the command exists because the screen did not.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 *
 * It does not make a module disappear. `ModuleService::setEnabled()` already
 * refuses to disable a required module and throws a LogicException with a
 * reason; that exception is caught below and shown, because a 500 for "you may
 * not do this" tells an operator nothing. The same refusal also means a
 * required module never reaches the disabled state through this screen, so the
 * "required" badge is information rather than a warning about something that
 * might happen.
 */
class ModuleController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly ModuleService $modules,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function index(): View
    {
        $this->authorize('viewAny', Module::class);

        return view('admin.modules.index', [
            'modules' => Module::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Module $module): RedirectResponse
    {
        $this->authorize('update', $module);

        $enabled = $request->boolean('is_enabled');

        /*
         * The service is the authority and it throws. Catching here rather than
         * letting it bubble means the operator is told WHICH module is required
         * and why, instead of receiving a 500 page and filing a bug report
         * about a button that "does nothing".
         */
        try {
            $this->modules->setEnabled($module->key, $enabled);
        } catch (\LogicException $e) {
            return $this->backWith($e->getMessage(), 'error');
        } catch (\InvalidArgumentException $e) {
            // A key the registry does not know. Route binding normally makes
            // this unreachable, but a renamed key in a database the code has
            // moved past reaches here — and an operator is not the right
            // recipient of a stack trace.
            return $this->backWith('Modul ini tidak dikenal oleh sistem.', 'error');
        }

        /*
         * ModuleService caches the enabled set for the request. Forget the
         * cache so the NEXT navigation build — which is composed on every
         * render — reflects what the operator just did. Without this the page
         * they land on still shows the old state, and the natural reading is
         * "the toggle did not save".
         */
        $this->modules->forget();

        $this->audit->log('module.toggled', $module, sprintf(
            '%s modul %s',
            $enabled ? 'Mengaktifkan' : 'Menonaktifkan',
            $module->name
        ), ['key' => $module->key, 'is_enabled' => $enabled]);

        return $this->backWith(sprintf(
            'Modul %s %s.',
            $module->name,
            $enabled ? 'diaktifkan' : 'dinonaktifkan'
        ));
    }
}
