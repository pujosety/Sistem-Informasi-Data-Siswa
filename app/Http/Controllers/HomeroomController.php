<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\ClassScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Kelas Saya" — the homeroom teacher's own landing page.
 *
 * The list is derived from active homeroom assignments, never from a
 * permission alone, so a Wali Kelas only ever sees the classes actually theirs.
 */
class HomeroomController extends Controller
{
    public function __construct(
        private readonly ClassScope $scope,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $classrooms = $this->scope->homeroomClassroomsFor($request->user());

        $summaries = $classrooms->map(function (SchoolClass $classroom) {
            $enrollments = $classroom->liveEnrollments()->with('student.registration')->get();

            $todaySession = $classroom->attendanceSessions()
                ->whereDate('date', today())
                ->with('records')
                ->first();

            $present = $todaySession
                ? $todaySession->records->whereIn('status', AttendanceRecord::PRESENT_STATUSES)->count()
                : null;

            return [
                'classroom' => $classroom,
                'students' => $enrollments->count(),
                'present_today' => $present,
                'recorded_today' => $todaySession?->records->count(),
                'incomplete' => $enrollments->filter(
                    fn ($e) => ($e->student->registration?->completeness ?? 100) < 100
                )->count(),
                'announcements' => $classroom->announcements()
                    ->whereNotNull('published_at')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
            ];
        });

        return view('academic.homeroom.index', [
            'summaries' => $summaries,
            'totalStudents' => $summaries->sum('students'),
            'year' => AcademicYear::current(),
            'statuses' => AttendanceRecord::STATUSES,
        ]);
    }
}
