<?php

namespace Tests;

use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Spatie stores roles in the database, so any test touching authorization
     * needs them present. Doing it centrally avoids every suite re-implementing
     * the same setUp and silently failing with RoleDoesNotExist.
     */
    protected function seedRoles(): void
    {
        // A bare role is NOT enough any more: routes are guarded by
        // `can:<permission>` and Gate::before only short-circuits for Super
        // Admin. Seeding the real catalogue keeps these tests honest instead of
        // relying on authorization that was silently skipping.
        app(\App\Services\RoleSeeder::class)->run();

        // Guarantee the roles these suites reference exist even if the
        // catalogue ever stops listing one.
        foreach (['super_admin', 'admin', 'kesiswaan', 'operator', 'verifikator', 'wali_kelas', 'siswa'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    protected function seedAcademicYear(): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['name' => now()->year.'/'.(now()->year + 1)],
            [
                'start_date' => now()->startOfYear(),
                'end_date' => now()->addYear()->endOfYear(),
                'is_active' => true,
            ]
        );
    }

    protected function seedDocumentTypes(): void
    {
        foreach ([
            ['Foto Siswa', 'foto-3x4', true, 10],
            ['Kartu Keluarga', 'kk', true, 20],
            ['Akta Kelahiran', 'akta-kelahiran', true, 30],
            ['Ijazah / SKL', 'ijazah', true, 40],
            ['KTP Orang Tua', 'ktp-ortu', true, 50],
        ] as [$name, $slug, $required, $order]) {
            DocumentType::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name, 'label' => $name,
                    'accepted_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
                    'max_size_kb' => 2048, 'is_required' => $required,
                    'is_active' => true, 'sort_order' => $order,
                ]
            );
        }
    }

    protected function makeUser(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * A small but genuinely valid PDF for upload tests.
     *
     * The previous version built a ~500 KB file from $kb * 30, which overflowed
     * the 2 MB document limit once the type's max_size_kb was applied and made
     * every upload test fail with "Ukuran berkas melebihi batas".
     */
    protected function makeUploadedPdf(string $name = 'dokumen.pdf'): \Illuminate\Http\UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sida').'.pdf';

        $pdf = "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";

        file_put_contents($path, $pdf);

        return new \Illuminate\Http\UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
