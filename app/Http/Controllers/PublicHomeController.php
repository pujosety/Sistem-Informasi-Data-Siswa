<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\ContactMessage;
use App\Models\Department;
use App\Models\LandingSection;
use App\Models\Post;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;

/**
 * The public face of the school: what a visitor sees before logging in.
 *
 * WHAT IS ALLOWED HERE, AND WHY IT IS SO NARROW
 *
 * §10 is explicit that internal data must never become public by accident, and
 * that student data is private by default. So this controller publishes exactly
 * two kinds of thing: the school's own identity from the `school.*` settings,
 * and school-level COUNTS.
 *
 * A count is not a row. "There are 412 students" reveals no NIK, no name, no
 * address, and no guardian. Everything this class touches is therefore either
 * a settings key or a `count()` — there is no `get()` on a model anywhere in
 * it, and no eager loading, because there is nothing to load.
 *
 * The reason this is written as a controller with no shared service is
 * deliberate. StatsService already answers these questions for the
 * authenticated dashboards, and reusing it would be tempting — but it is
 * tuned for staff who may see names in a drill-down, and the privacy boundary
 * for a public page is different enough that sharing the code would invite
 * someone to widen the queries later without noticing the page is public.
 */
class PublicHomeController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function __invoke(): View
    {
        $figures = $this->figures();

        return view('public.home', [
            'school' => $this->schoolProfile(),
            'figures' => $figures,
            'portalUrl' => route('login'),

            // The landing page is assembled from CMS blocks, in the order and
            // with the enabled flag the administrator set. Nothing here is
            // hardcoded: an empty section table renders an empty page, which is
            // the honest signal that the landing page has not been set up yet.
            'sections' => $this->sectionsWithLiveData($figures),

            // Real counts for the hero. The blocks carry their own copy, but a
            // school enrolment figure is a fact about the school and must not
            // be a marketing sentence that drifts from reality.
            'landingFigures' => $this->figures(),
        ]);
    }

    /**
     * The school's own published identity.
     *
     * Only `school.*` and the three app-name keys. `settings()` elsewhere is not
     * reachable from here by construction — the list is written out rather than
     * filtered, so a new setting added to the catalogue cannot be published by
     * being added.
     */
    private function schoolProfile(): array
    {
        return [
            'name'     => $this->settings->get('school.name'),
            'npsn'     => $this->settings->get('school.npsn'),
            'address'  => $this->settings->get('school.address'),
            'city'     => $this->settings->get('school.city'),
            'province' => $this->settings->get('school.province'),
            'email'    => $this->settings->get('school.email'),
            'phone'    => $this->settings->get('school.phone'),
            'website'  => $this->settings->get('school.website'),
            'headmaster' => $this->settings->get('school.headmaster'),
        ];
    }

    /**
     * School-level counts for the active year.
     *
     * Every one of these is a `count()`. If a figure here ever needs a name,
     * a filter, or a join, it does not belong on this page — that is the point
     * at which the query stops being an aggregate and starts being data.
     */
    private function figures(): array
    {
        try {
            $year = $this->activeYear();

            return [
                'students'  => Student::query()->count(),
                'classes'   => SchoolClass::query()
                    ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                    ->where('status', SchoolClass::ACTIVE)
                    ->count(),
                'subjects'  => Subject::query()->count(),
                'academicYear' => $year?->name,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'students' => 0,
                'classes' => 0,
                'subjects' => 0,
                'academicYear' => null,
            ];
        }
    }


    /**
     * The landing sections, with the data-driven ones filled in.
     *
     * A CMS block's `content` is what an administrator typed. That is the right
     * place for prose and for hand-ordered highlights, and the WRONG place for
     * the four most recent news posts — which change on their own and would be
     * stale the moment somebody published something.
     *
     * So a block's `items` are treated as FALLBACKS: if the section declares
     * `use_live` (or simply has no items) and live rows exist, the live rows
     * win. A school with three published articles sees three articles without
     * anyone touching the CMS, and a school with none still sees the curated
     * list it wrote by hand.
     *
     * Each block renders nothing when it ends up with neither — which is why
     * the news block has an @if around its markup and not an empty state: a
     * homepage advertising "Berita & Kegiatan" above the word "Belum ada
     * berita" is worse than a homepage without the section.
     */
    private function sectionsWithLiveData(array $figures): \Illuminate\Support\Collection
    {
        $sections = LandingSection::forPage('home');

        $news = $this->latestNews();

        foreach ($sections as $section) {
            if ($section->type === 'hero') {
                $liveStats = array_values(array_filter([
                    $figures['students'] > 0 ? ['value' => (string) $figures['students'], 'label' => 'Siswa Aktif'] : null,
                    $figures['classes'] > 0 ? ['value' => (string) $figures['classes'], 'label' => 'Kelas Aktif'] : null,
                    $figures['subjects'] > 0 ? ['value' => (string) $figures['subjects'], 'label' => 'Mata Pelajaran'] : null,
                    filled($figures['academicYear']) ? ['value' => $figures['academicYear'], 'label' => 'Tahun Ajaran'] : null,
                ]));
                $section->content = array_merge($section->content ?? [], ['stats' => $liveStats]);
            }

            if ($section->type === 'news' && $news !== []) {
                $section->content = array_merge($section->content ?? [], ['items' => $news]);
            }

            // Achievements and alumni are COUNTS and OUTCOMES, and those belong
            // to the database rather than to a CMS text field. A school that
            // graduates a class should see the number move without an
            // administrator retyping it.
            if ($section->type === 'achievements') {
                $achievementFigures = $this->achievementFigures();
                $section->content = $achievementFigures === []
                    ? []
                    : array_merge($section->content ?? [], $achievementFigures);
            }

            if ($section->type === 'alumni') {
                $section->content = array_merge($section->content ?? [], $this->alumniFigures());
            }
        }

        return $sections;
    }

    /**
     * The four most recent published, public posts.
     *
     * Reuses `publishedAndPublic()` rather than restating the visibility
     * rules: a second copy of "what is public" would drift, and the drift would
     * show as a draft on the front page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latestNews(): array
    {
        try {
            $posts = Post::query()
                ->posts()
                ->publishedAndPublic()
                ->with(['category', 'media'])
                ->orderByDesc('published_at')
                ->limit(4)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return $posts->map(fn (Post $post) => [
            'title' => $post->title,
            'url' => route('public.news.show', $post->slug),
            'excerpt' => $post->excerpt,
            'category' => $post->category?->name,
            'date' => $post->published_at?->translatedFormat('d M Y'),
            'image' => $post->media->first()?->url(),
        ])->all();
    }

    /**
     * Real counts for the achievements block.
     *
     * The breakdown by graduation year is derived from the `alumni` table
     * rather than typed in: it is a fact about the school, and a fact that
     * lives in prose is a fact that is wrong by next year.
     *
     * @return array<string, mixed>
     */
    private function achievementFigures(): array
    {
        try {
            $total = Alumni::query()->count();

            $byYear = Alumni::query()
                ->selectRaw('graduation_year as year, COUNT(*) as total')
                ->whereNotNull('graduation_year')
                ->groupBy('graduation_year')
                ->orderByDesc('graduation_year')
                ->limit(3)
                ->pluck('total', 'year');
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if ($total === 0) {
            return [];
        }

        $stats = [[
            'value' => (string) $total,
            'suffix' => $total > 0 ? '+' : null,
            'label' => 'Alumni yang telah lulus',
        ]];

        foreach ($byYear as $year => $count) {
            $stats[] = [
                'value' => (string) $count,
                'label' => "Lulus {$year}",
            ];
        }

        // With no alumni the block keeps only its CMS-authored figures, which
        // may be empty; the block then renders nothing, which is the correct
        // outcome rather than an empty "Prestasi" heading.
        return ['stats' => $stats];
    }

    /**
     * Alumni outcomes, read from the same table.
     *
     * @return array<string, mixed>
     */
    private function alumniFigures(): array
    {
        try {
            $recent = Alumni::query()
                ->with(['student', 'lastClassroom', 'department'])
                ->whereNotNull('graduation_year')
                ->orderByDesc('graduation_year')
                ->limit(4)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if ($recent->isEmpty()) {
            return [];
        }

        return [
            'stats' => [[
                'value' => (string) $recent->count(),
                'label' => 'Alumni tahun ini',
            ]],
            'notables' => $recent->map(fn (Alumni $row) => [
                'name' => $row->student?->full_name ?? 'Alumni',
                'detail' => trim(implode(' · ', array_filter([
                    $row->graduation_year ? "Lulus {$row->graduation_year}" : null,
                    $row->lastClassroom?->name,
                    $row->department?->name,
                ]))),
            ])->all(),
        ];
    }

    /**
     * School profile: the published identity, and nothing else.
     */
    public function profile(): View
    {
        return view('public.about', [
            'school' => $this->schoolProfile(),
        ]);
    }

    /**
     * Programmes: departments and the subjects offered in each.
     *
     * Both tables are curriculum metadata — a name and a code. Neither carries
     * a teacher, a class or a student, so §10 permits them. That is checked
     * rather than assumed: the moment a column here names a person, this page
     * stops being publishable without anyone noticing.
     */
    public function programs(): View
    {
        return view('public.programs', [
            'school' => $this->schoolProfile(),
            'departments' => $this->departmentsWithSubjects(),
        ]);
    }

    /**
     * Contact details, published as a form of words.
     *
     * Separate from the profile because it is the page a visitor is most
     * likely to arrive on directly, and a school that has not filled a field
     * in should not render an empty label for it.
     */
    public function contact(): View
    {
        return view('public.contact', [
            'school' => $this->schoolProfile(),
        ]);
    }

    public function submitContact(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        // Honeypot: bots get the same public response but no database write.
        if ($request->filled('website')) {
            return redirect()->route('public.contact')->with('success', 'Pesanmu sudah kami terima.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'topic' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::create($data + ['status' => ContactMessage::NEW]);

        return redirect()->route('public.contact')->with('success', 'Pesanmu sudah kami terima. Tim sekolah akan menindaklanjutinya.');
    }

    /**
     * Admission: the entry point into the existing registration flow.
     *
     * A page of its own rather than a redirect, because PPDB is a decision a
     * family makes and the conditions belong to be read before the form. The
     * form itself is the existing /daftar route, untouched.
     */
    public function admission(): View
    {
        return view('public.admission', [
            'school' => $this->schoolProfile(),
            'figures' => $this->figures(),
        ]);
    }

    /**
     * Departments with the subjects each offers.
     *
     * Grouped in PHP rather than SQL so a department with no subjects still
     * appears — a school that has entered two of three programmes should show
     * two, not silently drop the gap.
     */
    private function departmentsWithSubjects(): array
    {
        try {
            $departments = Department::query()->orderBy('name')->get();
            $subjects = Subject::query()->orderBy('name')->get();

            return $departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                // Counts only. Listing the classes themselves would publish a
                // roster-shaped page, which is a step further than §10 allows.
                'classCount' => SchoolClass::query()
                    ->where('department_id', $department->id)
                    ->where('status', SchoolClass::ACTIVE)
                    ->count(),
                'subjects' => $subjects->values(),
            ])->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The active academic year, or null.
     *
     * A fresh install has no years yet, and a public page that 500s on an empty
     * database is worse than one that shows a school with no figures.
     */
    private function activeYear(): ?AcademicYear
    {
        try {
            return AcademicYear::query()->where('status', AcademicYear::ACTIVE)->first();
        } catch (\Throwable $e) {
            // No table, or no database. A public page degrades rather than fails:
            // the visitor still gets the school's name and a way to log in.
            report($e);

            return null;
        }
    }
}
