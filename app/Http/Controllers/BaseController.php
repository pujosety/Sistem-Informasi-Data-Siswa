<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\StatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class BaseController extends Controller
{
    public function __construct(protected AuditService $audit, protected CompletenessService $completeness) {}

    protected function backWith(string $message, string $level = 'success'): RedirectResponse
    {
        // back() throws when the request has no referer AND no session
        // (API/CLI/test callers). Fall back to a safe default so a flash
        // message can never turn into a 500.
        try {
            return back()->with($level, $message);
        } catch (\Throwable) {
            return redirect()->route('dashboard')->with($level, $message);
        }
    }

    /** Shared filter options used by every admin/kesiswaan index table. */
    protected function filterOptions(): array
    {
        return [
            'statuses' => Registration::query()
                ->select('status')
                ->distinct()
                ->pluck('status'),
            'classes' => SchoolClass::orderBy('name')->pluck('name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'years' => AcademicYear::orderByDesc('name')->pluck('name', 'id'),
        ];
    }
}
