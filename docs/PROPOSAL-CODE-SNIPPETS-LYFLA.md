# Cuplikan Kode Aktual LYFLA untuk Proposal Capstone

Dokumen ini berisi cuplikan pendek dari repository LYFLA pada branch pengembangan. Semua snippet diambil dari implementasi aktual, bukan contoh generik. Credential, token, password, dan data pribadi tidak disertakan.

## Status audit fitur

- Routing public, dashboard, PPDB, CMS, LMS: tersedia.
- Model Student, Enrollment, Course, CourseEnrollment: tersedia.
- Statistik dashboard berbasis aggregation: tersedia.
- Authorization berbasis permission dan policy: tersedia.
- CMS publication gate: tersedia.
- Validasi pendaftaran siswa: tersedia.
- Export siswa: tersedia.
- Assignment submission LMS terpisah: **[FITUR BELUM TERIMPLEMENTASI]**.
- Perhitungan course progress lesson/submission yang berdiri sendiri: **[FITUR BELUM TERIMPLEMENTASI]**.

---

## KODE 1 - Routing Website Publik dan CMS

**File:** `bootstrap/app.php`

**Fungsi:** Mendefinisikan rute profil, program, PPDB, kontak, berita, pencarian, dan halaman CMS publik. Rute berita memakai controller CMS dan publication gate.

```php
Route::get('/tentang', [\App\Http\Controllers\PublicHomeController::class, 'profile'])
    ->name('public.about');
Route::get('/tentang/{slug}', [\App\Http\Controllers\PublicCmsController::class, 'page'])
    ->where('slug', '[a-z0-9-]+')
    ->name('public.page');

Route::get('/program', [\App\Http\Controllers\PublicHomeController::class, 'programs'])
    ->name('public.programs');
Route::get('/ppdb', [\App\Http\Controllers\PublicHomeController::class, 'admission'])
    ->name('public.admission');
Route::get('/kontak', [\App\Http\Controllers\PublicHomeController::class, 'contact'])
    ->name('public.contact');
Route::post('/kontak', [\App\Http\Controllers\PublicHomeController::class, 'submitContact'])
    ->middleware('throttle:5,1')
    ->name('public.contact.submit');

Route::get('/berita', [\App\Http\Controllers\PublicCmsController::class, 'news'])
    ->name('public.news');
Route::get('/cari', [\App\Http\Controllers\PublicCmsController::class, 'search'])
    ->name('public.search');
Route::get('/berita/{slug}', [\App\Http\Controllers\PublicCmsController::class, 'post'])
    ->name('public.news.show');
```

---

## KODE 2 - Model Student dan Relasi Utama

**File:** `app/Models/Student.php`

**Fungsi:** Menentukan field siswa yang dapat diisi, casting data, serta relasi ke akun, orang tua, tahun ajaran, enrollment, wali, alumni, dan pendaftaran.

```php
protected $fillable = [
    'user_id', 'nisn', 'nik', 'full_name', 'gender', 'birth_place', 'birth_date',
    'religion', 'phone', 'address', 'village', 'district', 'city', 'province', 'postal_code',
    'previous_school', 'graduation_year', 'diploma_number', 'previous_score',
    'class_id', 'academic_year_id', 'entry_year',
];

protected $casts = [
    'birth_date' => 'date',
    'previous_score' => 'decimal:2',
];

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function enrollments(): HasMany
{
    return $this->hasMany(Enrollment::class);
}

public function registration(): HasOne
{
    return $this->hasOne(Registration::class);
}
```

---

## KODE 3 - Enrollment sebagai Riwayat Kelas Siswa

**File:** `app/Models/Enrollment.php`

**Fungsi:** Menjadikan enrollment sebagai sumber data keanggotaan kelas per tahun ajaran, sehingga riwayat kenaikan kelas dan perpindahan tidak menimpa data lama.

```php
public const LIVE_STATUSES = ['active', 'promoted', 'retained', 'completed'];

protected $fillable = [
    'student_id',
    'academic_year_id',
    'classroom_id',
    'department_id',
    'status',
    'started_at',
    'ended_at',
    'notes',
    'source',
    'created_by',
];

public function student(): BelongsTo
{
    return $this->belongsTo(Student::class);
}

public function classroom(): BelongsTo
{
    return $this->belongsTo(SchoolClass::class, 'classroom_id');
}

public function scopeLive(Builder $query): Builder
{
    return $query->whereIn('status', self::LIVE_STATUSES);
}
```

---

## KODE 4 - Service Penempatan Siswa dengan Transaction

**File:** `app/Services/EnrollmentService.php`

**Fungsi:** Menempatkan siswa ke kelas melalui validasi domain dan transaction database.

```php
public function assign(
    Student $student,
    SchoolClass $classroom,
    ?User $actor = null,
    ?string $notes = null,
    string $source = 'manual',
): Enrollment {
    $check = $this->checkAssignable($student, $classroom);

    if (! $check['ok']) {
        throw ValidationException::withMessages(['student_id' => $check['reason']]);
    }

    $enrollment = DB::transaction(fn () => Enrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $classroom->academic_year_id,
        'classroom_id' => $classroom->id,
        'department_id' => $classroom->department_id,
        'status' => Enrollment::ACTIVE,
        'started_at' => now()->toDateString(),
        'notes' => $notes,
        'source' => $source,
        'created_by' => $actor?->id,
    ]));
```

---

## KODE 5 - Statistik Dashboard Berbasis Source of Truth

**File:** `app/Services/StatsService.php`

**Fungsi:** Menggabungkan attendance, akademik, PPDB, LMS, breakdown siswa, dan insight untuk dashboard tanpa angka dummy.

```php
public function dashboardAnalytics(?int $academicYearId = null, string $range = '30d', int $attendanceThreshold = 75): array
{
    $range = in_array($range, ['today', '7d', '30d', 'month', 'semester', 'year'], true)
        ? $range : '30d';
    $attendanceThreshold = max(1, min(100, $attendanceThreshold));

    $from = match ($range) {
        'today' => now()->startOfDay(),
        '7d' => now()->subDays(6)->startOfDay(),
        'month' => now()->startOfMonth(),
        'semester' => now()->subMonths(6)->startOfDay(),
        'year' => now()->subYear()->startOfDay(),
        default => now()->subDays(29)->startOfDay(),
    };

    $attendance = $this->attendanceAnalytics($academicYearId, $from, now()->endOfDay(), $attendanceThreshold);
    $academic = $this->academicAnalytics($academicYearId);
    $admission = $this->admissionAnalytics($academicYearId);
    $lms = $this->lmsAnalytics($academicYearId);
```

---

## KODE 6 - Course LMS dan Relasinya

**File:** `app/Models/Course.php`

**Fungsi:** Memodelkan course berdasarkan tahun ajaran, semester, subject, kelas, guru, enrollment, dan lesson.

```php
protected $table = 'lms_courses';

protected $fillable = [
    'academic_year_id', 'semester_id', 'subject_id', 'classroom_id',
    'teacher_id', 'code', 'title', 'description', 'status',
];

public function academicYear(): BelongsTo
{
    return $this->belongsTo(AcademicYear::class);
}

public function classroom(): BelongsTo
{
    return $this->belongsTo(SchoolClass::class, 'classroom_id');
}

public function enrollments(): HasMany
{
    return $this->hasMany(CourseEnrollment::class);
}

public function lessons(): HasMany
{
    return $this->hasMany(Lesson::class)->orderBy('position');
}
```

---

## KODE 7 - LMS Enrollment Siswa

**File:** `app/Models/CourseEnrollment.php`

**Fungsi:** Menghubungkan siswa dengan course LMS dan menyimpan status serta waktu enrollment.

```php
class CourseEnrollment extends Model
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    protected $table = 'lms_course_enrollments';
    protected $fillable = ['course_id', 'student_id', 'enrolled_at', 'status'];
    protected $casts = ['enrolled_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

---

## KODE 8 - Authorization Course LMS

**File:** `app/Policies/CoursePolicy.php`

**Fungsi:** Menggabungkan permission LMS dengan ownership guru dan pembatasan scope kelas.

```php
public function view(User $user, Course $course): bool
{
    if (! $user->can('lms.course.view')) {
        return false;
    }

    return $user->isSuperAdmin()
        || $course->teacher_id === $user->id
        || app(ClassScope::class)->canView($user, $course->classroom_id);
}

public function update(User $user, Course $course): bool
{
    if (! $user->can('lms.course.update')) {
        return false;
    }

    return $user->isSuperAdmin()
        || $course->teacher_id === $user->id
        || app(ClassScope::class)->canView($user, $course->classroom_id);
}
```

---

## KODE 9 - Pembuatan Course sebagai Draft

**File:** `app/Http/Controllers/Lms/CourseController.php`

**Fungsi:** Memvalidasi metadata course dan membuat course baru dengan status draft.

```php
$this->authorize('create', Course::class);

$data = $request->validate([
    'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
    'semester_id' => ['required', 'integer', 'exists:semesters,id'],
    'subject_id' => ['required', 'integer', 'exists:subjects,id'],
    'classroom_id' => ['required', 'integer', 'exists:classes,id'],
    'code' => ['required', 'string', 'max:80', 'unique:lms_courses,code'],
    'title' => ['required', 'string', 'max:180'],
    'description' => ['nullable', 'string'],
]);

$course = Course::create($data + [
    'teacher_id' => $request->user()->id,
    'status' => Course::DRAFT,
]);
```

---

## KODE 10 - Publication Gate CMS

**File:** `app/Models/Post.php`

**Fungsi:** Mencegah draft, artikel private, artikel terjadwal, dan artikel di luar jendela publikasi tampil di website.

```php
public function scopePublishedAndPublic(Builder $query): Builder
{
    $now = now();

    return $query
        ->where('status', self::PUBLISHED)
        ->where('is_public', true)
        ->whereNotNull('published_at')
        ->where('published_at', '<=', $now)
        ->where(fn ($q) => $q->whereNull('public_from')->orWhere('public_from', '<=', $now))
        ->where(fn ($q) => $q->whereNull('public_until')->orWhere('public_until', '>', $now));
}
```

---

## KODE 11 - Validasi Pendaftaran Peserta Didik

**File:** `app/Http/Requests/StudentRegistrationRequest.php`

**Fungsi:** Memvalidasi identitas akun, email unik, password, NISN 10 digit, persetujuan syarat, dan normalisasi input.

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
        'password' => ['required', 'confirmed', 'min:8', 'max:72'],
        'nisn' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/', Rule::unique('students', 'nisn')],
        'terms' => ['accepted'],
    ];
}

protected function prepareForValidation(): void
{
    $this->merge([
        'email' => mb_strtolower(trim((string) $this->input('email'))),
        'nisn' => preg_replace('/\D/', '', (string) $this->input('nisn')),
    ]);
}
```

---

## KODE 12 - Permission Role Administrator

**File:** `app/Services/PermissionCatalog.php`

**Fungsi:** Menentukan grant permission default untuk role administrator, termasuk siswa, PPDB, laporan, pengaturan, CMS, dan LMS.

```php
'admin' => $has(
    'dashboard.admin',
    'student.view', 'student.update', 'student.export',
    'registration.view', 'registration.update',
    'document.view', 'document.download', 'document.verify',
    'verification.view', 'verification.approve', 'verification.request_revision',
    'report.view', 'report.export',
    'master.view', 'master.create', 'master.update',
    'activity.view',
    'settings', 'branding', 'school',
    'module', 'employee',
    'academic_year', 'classroom', 'enrollment', 'homeroom',
    'guardian', 'attendance', 'grade', 'alumni', 'announcement',
    'cms',
    'lms',
),
```

---

## KODE 13 - Export Data Siswa ke Spreadsheet

**File:** `app/Services/Exports/StudentExport.php`

**Fungsi:** Menyusun heading dan mapping data siswa untuk proses export berbasis query.

```php
class StudentExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly \Illuminate\Database\Eloquent\Builder $query,
        private readonly string $title,
    ) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'NISN', 'NIK', 'Nama Lengkap', 'L/P', 'Tempat Lahir', 'Tanggal Lahir',
            'Agama', 'Telepon', 'Alamat', 'Kota', 'Asal Sekolah', 'Tahun Lulus',
            'No. Ijazah', 'Angkatan', 'Kelas', 'Jurusan', 'Kelengkapan (%)', 'Status',
        ];
    }
}
```

---

## KODE 14 - Komponen UI Form Reusable

**File:** `resources/views/components/form-field.blade.php`

**Fungsi:** Menyatukan label, input, error state, hint, atribut aksesibilitas, dan tipe field dalam satu komponen Blade.

```blade
@if ($type === 'textarea')
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows ?? 3 }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $fieldAttributes }}
        class="field @if ($hasError) field-error @endif"
    >{{ old($name, $value) }}</textarea>
@elseif ($type === 'select')
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $fieldAttributes }}
        class="field @if ($hasError) field-error @endif"
    >
        {{ $slot }}
    </select>
@endif
```

---

## KODE 15 - Course Progress dan Assignment

**Status audit:** Repository saat ini memiliki `Course`, `Lesson`, `CourseEnrollment`, dan relasi LMS dasar. Model/service khusus untuk `Assignment`, `AssignmentSubmission`, serta perhitungan progress berbasis lesson/submission belum ditemukan pada audit ini.

```text
[FITUR BELUM TERIMPLEMENTASI]
```

Snippet ini sengaja tidak dibuat-buat agar proposal tetap merepresentasikan kondisi sistem yang sebenarnya.
