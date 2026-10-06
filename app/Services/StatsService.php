<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatsService
{
    public function registrationSummary(?int $academicYearId = null): array
    {
        $base = Registration::query()
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));

        return [
            'total' => (clone $base)->count(),
            'verified' => (clone $base)->where('status', Registration::STATUS_VERIFIED)->count(),
            'pending' => (clone $base)->whereIn('status', [Registration::STATUS_PENDING, Registration::STATUS_SUBMITTED])->count(),
            'revision' => (clone $base)->whereIn('status', [Registration::STATUS_REVISION, Registration::STATUS_REJECTED])->count(),
            'draft' => (clone $base)->where('status', Registration::STATUS_DRAFT)->count(),
        ];
    }

    /**
     * Headline counts for the admin dashboard.
     *
     * Each is a COUNT rather than a loaded collection on purpose: the dashboard
     * only ever renders the number, and a school with 4,000 students would
     * otherwise hydrate 4,000 models to display one figure.
     *
     * `activeClasses` is scoped to the academic year when one is chosen, so the
     * card answers "how many classes are running now" rather than "how many
     * class rows have ever existed" — including the archived years, which is a
     * number no school wants on its dashboard.
     */
    public function headlineCounts(?int $academicYearId = null): array
    {
        $studentCount = Student::query()
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->count();

        // Enrollments are the authoritative roster. The compatibility fallback is
        // only for installations that have the table but have not backfilled it.
        if (Schema::hasTable('enrollments') && DB::table('enrollments')->exists()) {
            $studentCount = DB::table('enrollments')
                ->whereIn('status', ['active', 'promoted', 'retained', 'completed'])
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->distinct()
                ->count('student_id');
        }

        return [
            'students' => $studentCount,
            'teachers' => Employee::query()->whereIn('employment_status', ['active', 'on_leave'])->count(),
            'classes' => SchoolClass::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->count(),
        ];
    }

    /** Penders per day for the last N days. */
    public function dailyRegistrations(int $days = 30): array
    {
        return Registration::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->all();
    }

    public function byStatus(): array
    {
        return Registration::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function byClass(): array
    {
        return SchoolClass::query()
            ->withCount('students')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['class' => $c->name, 'total' => $c->students_count])
            ->all();
    }

    public function byDepartment(): array
    {
        return DB::table('students')
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->join('departments', 'departments.id', '=', 'classes.department_id')
            ->select('departments.name', DB::raw('COUNT(students.id) as total'))
            ->groupBy('departments.name')
            ->orderBy('departments.name')
            ->get()
            ->map(fn ($r) => ['department' => $r->name, 'total' => $r->total])
            ->all();
    }

    public function byGender(): array
    {
        return Student::query()
            ->selectRaw('gender, COUNT(*) as total')
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->all();
    }

    public function byEntryYear(): array
    {
        return Student::query()
            ->selectRaw('entry_year, COUNT(*) as total')
            ->whereNotNull('entry_year')
            ->groupBy('entry_year')
            ->orderBy('entry_year')
            ->pluck('total', 'entry_year')
            ->all();
    }

    /**
     * Build the decision-support payload for the global admin dashboard.
     *
     * Every figure is derived from source tables. Optional modules are reported
     * as unavailable instead of being filled with illustrative numbers.
     */
    public function dashboardAnalytics(?int $academicYearId = null, string $range = '30d', int $attendanceThreshold = 75): array
    {
        $range = in_array($range, ['today', '7d', '30d', 'month', 'semester', 'year'], true) ? $range : '30d';
        $attendanceThreshold = max(1, min(100, $attendanceThreshold));
        $from = match ($range) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            'month' => now()->startOfMonth(),
            'semester' => now()->subMonths(6)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
        $to = now()->endOfDay();

        $attendance = $this->attendanceAnalytics($academicYearId, $from, $to, $attendanceThreshold);
        $academic = $this->academicAnalytics($academicYearId);
        $admission = $this->admissionAnalytics($academicYearId);
        $lms = $this->lmsAnalytics($academicYearId);
        $breakdown = $this->studentBreakdown($academicYearId);
        $headline = $this->headlineCounts($academicYearId);
        $insights = [];

        if ($attendance['low_count'] > 0) {
            $insights[] = [
                'icon' => 'alert-triangle', 'tone' => 'warning',
                'title' => $attendance['low_count'].' siswa attendance rendah',
                'detail' => 'Di bawah '.$attendanceThreshold.'% pada rentang terpilih.',
                'action' => 'Review Students', 'href' => route('kesiswaan.students'),
            ];
        }
        if ($admission['incomplete'] > 0) {
            $insights[] = [
                'icon' => 'file-warning', 'tone' => 'warning',
                'title' => $admission['incomplete'].' PPDB belum lengkap',
                'detail' => 'Profil atau dokumen masih membutuhkan tindak lanjut.',
                'action' => 'Open Applications', 'href' => route('admin.registrations', ['status' => 'pending']),
            ];
        }
        if ($academic['low_class_count'] > 0) {
            $insights[] = [
                'icon' => 'trending-down', 'tone' => 'danger',
                'title' => $academic['low_class_count'].' kelas di bawah target nilai',
                'detail' => 'Rata-rata kelas di bawah 75 pada nilai terbit.',
                'action' => 'Review Academic', 'href' => route('admin.registrations'),
            ];
        }
        $lowSubjects = collect($academic['subjects_needing_attention'] ?? [])->filter(fn ($subject) => $subject['average'] < $academic['target']);
        if ($lowSubjects->isNotEmpty()) {
            $insights[] = [
                'icon' => 'book-open', 'tone' => 'warning',
                'title' => $lowSubjects->count().' mata pelajaran perlu perhatian',
                'detail' => 'Rata-rata subject berada di bawah target '.$academic['target'].'.',
                'action' => 'Review Academic', 'href' => route('admin.registrations'),
            ];
        }

        return [
            'range' => ['key' => $range, 'from' => $from, 'to' => $to],
            'kpis' => [
                'students' => $headline['students'],
                'teachers' => $headline['teachers'],
                'employees' => Employee::query()->count(),
                'classes' => $headline['classes'],
                'programs' => Schema::hasTable('departments') ? DB::table('departments')->count() : null,
                'applications' => $admission['applicants'],
                'active_courses' => $lms['available'] ? $lms['active_courses'] : null,
            ],
            'attendance' => $attendance,
            'academic' => $academic,
            'admission' => $admission,
            'lms' => $lms,
            'breakdown' => $breakdown,
            'insights' => $insights,
            'availability' => [
                'finance' => false, 'cms_traffic' => false,
                'employee_attendance' => false, 'lms_assignments' => false,
            ],
        ];
    }

    private function attendanceAnalytics(?int $yearId, $from, $to, int $threshold): array
    {
        if (! Schema::hasTable('attendance_records') || ! Schema::hasTable('attendance_sessions')) {
            return ['available' => false, 'rate' => null, 'target' => 95, 'trend' => [], 'statuses' => [], 'low_count' => 0, 'low_students' => [], 'by_class' => [], 'threshold' => $threshold];
        }

        $base = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ats', 'ats.id', '=', 'ar.attendance_session_id')
            ->when($yearId, fn ($q) => $q->where('ats.academic_year_id', $yearId))
            ->whereBetween('ats.date', [$from->toDateString(), $to->toDateString()]);
        $total = (clone $base)->count();
        $present = (clone $base)->whereIn('ar.status', ['present', 'late'])->count();
        $statuses = (clone $base)->select('ar.status', DB::raw('COUNT(*) as total'))->groupBy('ar.status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $trend = (clone $base)
            ->selectRaw("DATE(ats.date) as day, COUNT(*) as total, SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) as present")
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn ($row) => ['day' => (string) $row->day, 'rate' => $row->total > 0 ? round($row->present / $row->total * 100, 1) : null, 'total' => (int) $row->total])->all();
        $lowStudents = (clone $base)
            ->join('enrollments as en', 'en.id', '=', 'ar.enrollment_id')
            ->join('students as st', 'st.id', '=', 'en.student_id')
            ->leftJoin('classes as cl', 'cl.id', '=', 'en.classroom_id')
            ->select('st.id', 'st.full_name', 'cl.name as class_name')
            ->selectRaw('COUNT(ar.id) as attendance_total')
            ->selectRaw("SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) as attendance_present")
            ->groupBy('st.id', 'st.full_name', 'cl.name')->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->full_name, 'class' => $row->class_name ?: 'Tanpa kelas', 'rate' => (int) $row->attendance_total > 0 ? round($row->attendance_present / $row->attendance_total * 100, 1) : 0])
            ->filter(fn ($row) => $row['rate'] < $threshold)->sortBy('rate')->values();

        return [
            'available' => true, 'rate' => $total > 0 ? round($present / $total * 100, 1) : null,
            'target' => 95, 'trend' => $trend, 'statuses' => $statuses,
            'low_count' => $lowStudents->count(), 'low_students' => $lowStudents->take(8)->all(),
            'by_class' => $lowStudents->groupBy('class')->map(fn ($rows, $class) => ['class' => $class, 'total' => $rows->count()])->values()->take(6)->all(),
            'threshold' => $threshold,
        ];
    }

    private function academicAnalytics(?int $yearId): array
    {
        if (! Schema::hasTable('grades') || ! Schema::hasTable('enrollments') || ! Schema::hasTable('subjects')) {
            return ['available' => false, 'average' => null, 'median' => null, 'target' => 75, 'distribution' => [], 'subjects' => [], 'subjects_needing_attention' => [], 'classes' => [], 'classes_needing_attention' => [], 'trend' => [], 'low_class_count' => 0];
        }

        $base = DB::table('grades as gr')
            ->join('enrollments as en', 'en.id', '=', 'gr.enrollment_id')
            ->where('gr.status', 'published')
            ->whereNotNull('gr.score')
            ->when($yearId, fn ($q) => $q->where('en.academic_year_id', $yearId));
        $average = (clone $base)->avg('gr.score');
        $scores = (clone $base)->orderBy('gr.score')->pluck('gr.score')->map(fn ($score) => (float) $score)->values();
        $median = null;
        if ($scores->isNotEmpty()) {
            $middle = intdiv($scores->count(), 2);
            $median = $scores->count() % 2 === 0
                ? round(($scores[$middle - 1] + $scores[$middle]) / 2, 1)
                : round($scores[$middle], 1);
        }

        $distribution = (clone $base)
            ->selectRaw("CASE WHEN gr.score >= 90 THEN '90–100' WHEN gr.score >= 80 THEN '80–89' WHEN gr.score >= 70 THEN '70–79' ELSE '<70' END as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();
        $subjectRows = (clone $base)
            ->join('subjects as su', 'su.id', '=', 'gr.subject_id')
            ->select('su.name')
            ->selectRaw('AVG(gr.score) as average')
            ->groupBy('su.id', 'su.name')
            ->orderByDesc('average')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'average' => round((float) $row->average, 1)])
            ->values();
        $classRows = (clone $base)
            ->join('classes as cl', 'cl.id', '=', 'en.classroom_id')
            ->select('cl.name')
            ->selectRaw('AVG(gr.score) as average')
            ->groupBy('cl.id', 'cl.name')
            ->orderByDesc('average')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'average' => round((float) $row->average, 1)])
            ->values();
        $trend = (clone $base)
            ->select('gr.term')
            ->selectRaw('AVG(gr.score) as average')
            ->groupBy('gr.term')
            ->orderBy('gr.term')
            ->get()
            ->map(fn ($row) => ['label' => 'Term '.$row->term, 'average' => round((float) $row->average, 1)])
            ->all();

        return [
            'available' => true,
            'average' => $average !== null ? round((float) $average, 1) : null,
            'median' => $median,
            'target' => 75,
            'distribution' => $distribution,
            'subjects' => $subjectRows->take(8)->all(),
            'subjects_needing_attention' => $subjectRows->sortBy('average')->take(3)->values()->all(),
            'classes' => $classRows->take(10)->all(),
            'classes_needing_attention' => $classRows->sortBy('average')->take(3)->values()->all(),
            'trend' => $trend,
            'low_class_count' => $classRows->where('average', '<', 75)->count(),
        ];
    }

    private function admissionAnalytics(?int $yearId): array
    {
        $base = Registration::query()->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId));
        $applicants = (clone $base)->count();
        $profileCompleted = (clone $base)->where('completeness', '>=', 100)->count();
        $verified = (clone $base)->where('status', Registration::STATUS_VERIFIED)->count();
        $incomplete = (clone $base)->whereIn('status', [Registration::STATUS_PENDING, Registration::STATUS_SUBMITTED, Registration::STATUS_REVISION])->where('completeness', '<', 100)->count();
        return ['applicants' => $applicants, 'profile_completed' => $profileCompleted, 'verified' => $verified, 'incomplete' => $incomplete, 'conversion' => $applicants > 0 ? round($verified / $applicants * 100, 1) : null, 'stages' => [['label' => 'Applicants', 'value' => $applicants], ['label' => 'Profile Completed', 'value' => $profileCompleted], ['label' => 'Verified', 'value' => $verified]]];
    }

    private function lmsAnalytics(?int $yearId): array
    {
        if (! Schema::hasTable('lms_courses')) {
            return ['available' => false, 'active_courses' => null, 'enrollments' => null, 'published_lessons' => null, 'progress' => null];
        }
        $courses = DB::table('lms_courses')->where('status', 'published')->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId));
        $courseIds = (clone $courses)->pluck('id');
        $enrollments = Schema::hasTable('lms_course_enrollments') ? DB::table('lms_course_enrollments')->whereIn('course_id', $courseIds)->where('status', 'active')->count() : null;
        $lessons = Schema::hasTable('lms_lessons') ? DB::table('lms_lessons')->whereIn('course_id', $courseIds)->where('status', 'published')->count() : null;
        return ['available' => true, 'active_courses' => $courseIds->count(), 'enrollments' => $enrollments, 'published_lessons' => $lessons, 'progress' => null];
    }

    private function studentBreakdown(?int $yearId): array
    {
        $byGrade = [];
        $byClass = [];
        $byProgram = [];

        if (Schema::hasTable('enrollments') && Schema::hasTable('classes')) {
            $roster = DB::table('enrollments as en')
                ->join('students as st', 'st.id', '=', 'en.student_id')
                ->leftJoin('classes as cl', 'cl.id', '=', 'en.classroom_id')
                ->leftJoin('departments as de', 'de.id', '=', 'en.department_id')
                ->whereIn('en.status', ['active', 'promoted', 'retained', 'completed'])
                ->when($yearId, fn ($q) => $q->where('en.academic_year_id', $yearId));

            $byGrade = (clone $roster)
                ->select('cl.level as label')
                ->selectRaw('COUNT(DISTINCT en.student_id) as value')
                ->whereNotNull('cl.level')
                ->groupBy('cl.level')
                ->orderBy('cl.level')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])
                ->all();

            $byClass = (clone $roster)
                ->select('cl.name as label')
                ->selectRaw('COUNT(DISTINCT en.student_id) as value')
                ->whereNotNull('cl.name')
                ->groupBy('cl.id', 'cl.name')
                ->orderByDesc('value')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])
                ->all();

            $byProgram = (clone $roster)
                ->selectRaw('COALESCE(de.name, \'Tanpa program\') as label')
                ->selectRaw('COUNT(DISTINCT en.student_id) as value')
                ->groupBy('de.id', 'de.name')
                ->orderByDesc('value')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])
                ->all();
        }

        if ($byGrade === []) {
            $byGrade = Student::query()
                ->leftJoin('classes as cl', 'cl.id', '=', 'students.class_id')
                ->when($yearId, fn ($q) => $q->where('students.academic_year_id', $yearId))
                ->select('cl.level as label')
                ->selectRaw('COUNT(students.id) as value')
                ->whereNotNull('cl.level')
                ->groupBy('cl.level')
                ->orderBy('cl.level')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])
                ->all();
        }

        return [
            'by_grade' => $byGrade,
            'by_class' => $byClass,
            'by_program' => $byProgram,
            'by_gender' => $this->byGender(),
            'growth' => $this->studentGrowth($yearId),
        ];
    }

    private function studentGrowth(?int $yearId): array
    {
        if (! Schema::hasTable('academic_years')) {
            return ['available' => false, 'points' => [], 'change' => null, 'change_pct' => null];
        }

        $years = DB::table('academic_years')->select('id', 'name')->orderBy('start_date')->get();
        $points = $years->map(function ($year) {
            $count = Schema::hasTable('enrollments')
                ? DB::table('enrollments')
                    ->where('academic_year_id', $year->id)
                    ->whereIn('status', ['active', 'promoted', 'retained', 'completed'])
                    ->distinct()
                    ->count('student_id')
                : Student::query()->where('academic_year_id', $year->id)->count();

            return ['label' => $year->name, 'value' => (int) $count];
        })->values();
        $previous = $points->count() > 1 ? $points[$points->count() - 2]['value'] : null;
        $current = $points->last()['value'] ?? null;

        return [
            'available' => $points->isNotEmpty(),
            'points' => $points->all(),
            'change' => $previous !== null && $current !== null ? $current - $previous : null,
            'change_pct' => $previous ? round(($current - $previous) / $previous * 100, 1) : null,
        ];
    }
}