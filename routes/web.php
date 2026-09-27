<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\KesiswaanController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsureRole;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HomeroomController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/daftar', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Student portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsureRole::class.':siswa'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');

    // Multi-step wizard
    Route::get('/pendaftaran/{step?}', [StudentPortalController::class, 'wizard'])
        ->whereIn('step', ['akun', 'pribadi', 'orang-tua', 'pendidikan', 'dokumen', 'review'])
        ->name('wizard');
    Route::post('/pendaftaran', [StudentPortalController::class, 'saveStep'])->name('wizard.save');

    // Individual editors kept for deep links; the wizard is the primary flow.
    Route::get('/biodata', [StudentPortalController::class, 'editBiodata'])->name('biodata');
    Route::put('/biodata', [StudentPortalController::class, 'updateBiodata'])->name('biodata.update');
    Route::get('/orang-tua', [StudentPortalController::class, 'editParents'])->name('parents');
    Route::put('/orang-tua', [StudentPortalController::class, 'updateParents'])->name('parents.update');

    Route::get('/dokumen', [StudentPortalController::class, 'documents'])->name('documents');
    Route::post('/dokumen/{documentType}', [StudentPortalController::class, 'uploadDocument'])->name('documents.upload');
    Route::delete('/dokumen/{documentType}', [StudentPortalController::class, 'deleteDocument'])->name('documents.delete');

    Route::get('/status', [StudentPortalController::class, 'status'])->name('status');
    Route::post('/kirim', [StudentPortalController::class, 'submit'])->name('submit');
});

/*
|--------------------------------------------------------------------------
| Private document streaming (owner or staff only)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/berkas/{document}', [DocumentFileController::class, 'show'])->name('documents.show');
    Route::get('/berkas/{document}/unduh', [DocumentFileController::class, 'show'])->name('documents.download');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:dashboard.admin.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    Route::middleware('can:registration.view')->group(function () {
        Route::get('/pendaftaran', [AdminController::class, 'registrations'])->name('registrations');
        Route::get('/pendaftaran/{student}', [AdminController::class, 'showRegistration'])->name('registrations.show');
    });

    Route::middleware('can:registration.update')->group(function () {
        Route::put('/pendaftaran/{student}', [AdminController::class, 'updateStudent'])->name('registrations.update');
    });

    Route::middleware('can:document.verify')->group(function () {
        Route::post('/pendaftaran/{student}/dokumen/{document}', [AdminController::class, 'reviewDocument'])->name('documents.review');
    });

    // The catalogue names the decision permissions `verification.approve` and
    // `verification.request_revision`; the old guard asked for
    // `registration.verify`, which never existed. An unknown permission can
    // never grant access, so approving a registration always 403'd.
    Route::middleware('can:verification.approve')->group(function () {
        Route::post('/pendaftaran/{student}/keputusan', [AdminController::class, 'decideRegistration'])->name('decide');
    });

    // --- User management (ADMIN → PENGGUNA) -------------------------
    Route::middleware('can:user.view')->group(function () {
        Route::get('/pengguna', [UserManagementController::class, 'index'])->name('users');
        Route::get('/pengguna/{user}', [UserManagementController::class, 'show'])->name('users.show');
    });

    Route::middleware('can:user.create')->group(function () {
        Route::get('/pengguna/baru', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserManagementController::class, 'store'])->name('users.store');
    });

    Route::middleware('can:user.update')->group(function () {
        Route::get('/pengguna/{user}/ubah', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::put('/pengguna/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');
    });

    Route::middleware('can:user.disable')->group(function () {
        Route::post('/pengguna/{user}/status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle');
    });

    Route::middleware('can:user.reset_password')->group(function () {
        Route::put('/pengguna/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');
    });

    Route::middleware('can:user.delete')->group(function () {
        Route::delete('/pengguna/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

    // --- Roles & permissions (ADMIN → ROLE & HAK AKSES) -------------
    Route::middleware('can:role.view')->group(function () {
        Route::get('/role', [RoleManagementController::class, 'index'])->name('roles');
        Route::get('/role/{role}', [RoleManagementController::class, 'edit'])->name('roles.edit');
    });

    Route::middleware('can:role.update')->group(function () {
        Route::put('/role/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
    });

    Route::middleware('can:role.create')->group(function () {
        Route::post('/role', [RoleManagementController::class, 'store'])->name('roles.store');
    });

    Route::middleware('can:role.delete')->group(function () {
        Route::delete('/role/{role}', [RoleManagementController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('can:activity.view')->group(function () {
        Route::get('/log-aktivitas', [AdminController::class, 'activityLogs'])->name('activity');
    });

    Route::middleware('can:activity.view')->group(function () {
        Route::get('/log-aktivitas', [AdminController::class, 'activityLogs'])->name('activity');
    });

    Route::middleware('can:master.view')->group(function () {
        Route::get('/master-data', [MasterDataController::class, 'index'])->name('master');
    });

    Route::middleware('can:master.create')->group(function () {
        Route::post('/master-data/tahun-ajaran', [MasterDataController::class, 'storeYear'])->name('master.years');
        Route::post('/master-data/jurusan', [MasterDataController::class, 'storeDepartment'])->name('master.departments');
        Route::post('/master-data/kelas', [MasterDataController::class, 'storeClass'])->name('master.classes');
        Route::post('/master-data/jenis-dokumen', [MasterDataController::class, 'storeDocumentType'])->name('master.document-types');
    });
});

/*
|--------------------------------------------------------------------------
| Kesiswaan
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:student.view'])->prefix('kesiswaan')->name('kesiswaan.')->group(function () {
    Route::middleware('can:dashboard.admin.view')->group(function () {
        Route::get('/dashboard', [KesiswaanController::class, 'dashboard'])->name('dashboard');
    });

    Route::get('/data-siswa', [KesiswaanController::class, 'students'])->name('students');
    Route::get('/data-siswa/{student}', [KesiswaanController::class, 'show'])->name('students.show');
    Route::get('/statistik', [KesiswaanController::class, 'statistics'])->name('statistics');
    Route::get('/rekapitulasi', [KesiswaanController::class, 'rekap'])->name('rekap');
});

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:report.view'])->prefix('laporan')->name('laporan.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/preview', [ReportController::class, 'preview'])->name('preview');

    // Exporting is a distinct, higher-risk action than viewing.
    Route::middleware('can:report.export')->group(function () {
        Route::get('/excel', [ReportController::class, 'excel'])->name('excel');
        Route::get('/csv', [ReportController::class, 'csv'])->name('csv');
        Route::get('/pdf', [ReportController::class, 'pdf'])->name('pdf');
    });
});

/*
|--------------------------------------------------------------------------
| Shared
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (\App\Models\User $user) {
        $target = $user->homeRoute();

        // Guard against a homeRoute() that points back here, which would
        // otherwise loop until the browser gives up with too many redirects.
        return $target === 'dashboard'
            ? redirect()->route('profile.edit')
            : redirect()->route($target);
    })->name('dashboard');


/*
|--------------------------------------------------------------------------
| AKADEMIK — Tahun Ajaran, Kelas, Enrollment, Wali Kelas
|--------------------------------------------------------------------------
|
| Classroom routes are guarded by BOTH a permission and, inside the
| controllers, a ClassScope check. A Wali Kelas holding classroom.student.view
| still cannot open another teacher's class, because scope limits them to their
| own active homeroom assignment.
|
*/

Route::middleware(['auth', 'can:academic_year.view'])->prefix('akademik/tahun-ajaran')->name('academic.years.')->group(function () {
    Route::get('/', [AcademicYearController::class, 'index'])->name('index');
    Route::post('/', [AcademicYearController::class, 'store'])->name('store');
    Route::put('/{academicYear}', [AcademicYearController::class, 'update'])->name('update');
    Route::post('/{academicYear}/aktifkan', [AcademicYearController::class, 'activate'])->name('activate');
    Route::post('/{academicYear}/arsipkan', [AcademicYearController::class, 'archive'])->name('archive');
});

Route::middleware(['auth', 'can:classroom.view'])->prefix('akademik')->name('academic.')->group(function () {
    // --- Kelas -------------------------------------------------------
    Route::get('/kelas', [ClassroomController::class, 'index'])->name('classes.index');
    Route::get('/kelas/baru', [ClassroomController::class, 'create'])->name('classes.create');
    Route::post('/kelas', [ClassroomController::class, 'store'])->name('classes.store');
    Route::get('/kelas/{classroom}', [ClassroomController::class, 'show'])->name('classes.show');
    Route::get('/kelas/{classroom}/ubah', [ClassroomController::class, 'edit'])->name('classes.edit');
    Route::put('/kelas/{classroom}', [ClassroomController::class, 'update'])->name('classes.update');
    Route::post('/kelas/{classroom}/arsipkan', [ClassroomController::class, 'archive'])->name('classes.archive');
    Route::post('/kelas/{classroom}/wali-kelas', [ClassroomController::class, 'assignHomeroom'])->name('classes.homeroom');

    // --- Absensi -----------------------------------------------------
    Route::get('/kelas/{classroom}/absensi/{date?}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/kelas/{classroom}/absensi', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::post('/kelas/{classroom}/absensi/{session}/kunci', [AttendanceController::class, 'lock'])->name('attendance.lock');

    // --- Pengumuman --------------------------------------------------
    Route::get('/kelas/{classroom}/pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/kelas/{classroom}/pengumuman/baru', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/kelas/{classroom}/pengumuman', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/kelas/{classroom}/pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
});

// --- Penempatan & pemindahan siswa (permission-gated, scope-checked) -----
Route::middleware(['auth', 'can:enrollment.view'])->prefix('akademik')->name('academic.')->group(function () {
    Route::get('/penempatan', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('/penempatan/ringkasan', [EnrollmentController::class, 'preview'])->name('enrollments.preview');
    Route::post('/penempatan', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::get('/pindah/{student}', [EnrollmentController::class, 'move'])->name('enrollments.move');
    Route::post('/pindah/{student}', [EnrollmentController::class, 'storeMove'])->name('enrollments.move.store');
    Route::delete('/penempatan/{student}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
});

// --- KELAS SAYA (Wali Kelas) ---------------------------------------------
Route::middleware(['auth', 'can:classroom.view'])
    ->get('/kelas-saya', [HomeroomController::class, 'index'])
    ->name('academic.homeroom.index');

    // --- Settings (ADMIN → PENGATURAN) ------------------------------
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
    });

    Route::middleware('can:school.view')->group(function () {
        Route::get('/pengaturan/profil-sekolah', [SettingsController::class, 'school'])->name('settings.school');
    });

    Route::middleware('can:branding.view')->group(function () {
        Route::get('/pengaturan/branding', [SettingsController::class, 'branding'])->name('settings.branding');
    });

    Route::get('/pengaturan/pendaftaran', [SettingsController::class, 'registration'])->name('settings.registration');
    Route::get('/pengaturan/aplikasi', [SettingsController::class, 'application'])->name('settings.application');

    Route::middleware('can:settings.update')->group(function () {
        Route::put('/pengaturan/profil-sekolah', [SettingsController::class, 'updateSchool'])->name('settings.school.update');
        Route::put('/pengaturan/pendaftaran', [SettingsController::class, 'updateRegistration'])->name('settings.registration.update');
        Route::put('/pengaturan/aplikasi', [SettingsController::class, 'updateApplication'])->name('settings.application.update');
    });

    Route::middleware('can:branding.update')->group(function () {
        Route::put('/pengaturan/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');
        Route::delete('/pengaturan/branding/{key}', [SettingsController::class, 'removeAsset'])->name('settings.branding.remove');
    });

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
