<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\DocumentType;
use App\Models\ParentGuardian;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function __construct(
        private readonly CompletenessService $completeness,
        private readonly DocumentService $documents,
    ) {}

    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        $classes = SchoolClass::with('department')->get();

        // Match by "LEVEL CODE" prefix so demo rows survive renumbering of suffixes.
        $findClass = function (string $label) use ($classes) {
            $parts = preg_split('/\s+/', trim($label));

            return $classes->first(fn ($c) => $c->level === $parts[0]
                && str_starts_with((string) $c->department?->code, $parts[1] ?? ''));
        };

        $demo = [
            ['email' => 'siswa@siswa.test', 'nisn' => '0091234567', 'name' => 'Budi Santoso', 'gender' => 'L', 'class' => 'X IPA 1', 'status' => Registration::STATUS_DRAFT, 'completeness' => 35],
            ['email' => 'andi@siswa.test', 'nisn' => '0091234568', 'name' => 'Andi Pratama', 'gender' => 'L', 'class' => 'X TKJ 2', 'status' => Registration::STATUS_PENDING, 'completeness' => 100],
            ['email' => 'siti@siswa.test', 'nisn' => '0091234569', 'name' => 'Siti Nurhaliza', 'gender' => 'P', 'class' => 'XI IPS 1', 'status' => Registration::STATUS_VERIFIED, 'completeness' => 100],
            ['email' => 'rizky@siswa.test', 'nisn' => '0091234570', 'name' => 'Rizky Hidayat', 'gender' => 'L', 'class' => 'XII IPA 1', 'status' => Registration::STATUS_REVISION, 'completeness' => 85],
        ];

        foreach ($demo as $row) {
            $user = User::firstWhere('email', $row['email']);

            if (! $user) {
                continue;
            }

            $student = Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nisn' => $row['nisn'],
                    'full_name' => $row['name'],
                    'gender' => $row['gender'],
                    'birth_place' => 'Bandung',
                    'birth_date' => '2009-05-'.str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT),
                    'religion' => 'Islam',
                    'phone' => '0812'.random_int(10000000, 99999999),
                    'address' => 'Jl. Merdeka No. '.random_int(1, 200),
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'previous_school' => 'SMP Negeri 1 Bandung',
                    'graduation_year' => '2026',
                    'diploma_number' => 'SKL/'.random_int(1000, 9999),
                    'previous_score' => random_int(70, 95) + 0.5,
                    'class_id' => $findClass($row['class'])?->id,
                    'academic_year_id' => $year?->id,
                    'entry_year' => '2026',
                ]
            );

            foreach ([['father', 'Ayah '.$student->full_name, 'Petani'], ['mother', 'Ibu '.$student->full_name, 'Ibu Rumah Tangga']] as [$rel, $pname, $job]) {
                ParentGuardian::updateOrCreate(
                    ['student_id' => $student->id, 'relation' => $rel],
                    ['full_name' => $pname, 'job' => $job, 'phone' => '0813'.random_int(10000000, 99999999), 'address' => $student->address]
                );
            }

            $registration = Registration::firstOrCreate(
                ['student_id' => $student->id],
                ['academic_year_id' => $year?->id ?? 1, 'status' => Registration::STATUS_DRAFT, 'submitted_at' => now()]
            );

            $this->documents->seedPlaceholders($registration);

            if ($row['status'] !== Registration::STATUS_DRAFT) {
                $registration->update(['status' => $row['status']]);

                if ($row['status'] === Registration::STATUS_VERIFIED) {
                    $registration->update(['verified_at' => now(), 'verified_by' => User::where('email', 'admin@siswa.test')->value('id')]);
                    $registration->documents()->update(['status' => 'valid', 'reviewed_at' => now()]);
                } elseif ($row['status'] === Registration::STATUS_PENDING) {
                    $registration->documents()->update(['status' => 'pending', 'uploaded_at' => now()]);
                } elseif ($row['status'] === Registration::STATUS_REVISION) {
                    $ijazah = $registration->documents()->whereHas('documentType', fn ($q) => $q->where('slug', 'ijazah'))->first();

                    if ($ijazah) {
                        $ijazah->update([
                            'status' => 'rejected',
                            'rejection_reason' => 'Dokumen tidak terbaca dengan jelas. Silakan unggah ulang ijazah yang lebih tajam.',
                            'reviewed_at' => now(),
                        ]);
                    }

                    $registration->documents()->where('id', '!=', $ijazah?->id)->update(['status' => 'valid', 'uploaded_at' => now()]);
                    $registration->update(['admin_note' => 'Ijazah perlu diunggah ulang.']);
                }
            }

            $this->completeness->refresh($registration);
        }
    }
}
