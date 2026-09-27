<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DocumentType;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
        );

        AcademicYear::updateOrCreate(
            ['name' => '2025/2026'],
            ['start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => false]
        );

        $departments = collect([
            ['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA'],
            ['name' => 'Ilmu Pengetahuan Sosial', 'code' => 'IPS'],
            ['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ'],
            ['name' => 'Bahasa', 'code' => 'BHS'],
        ])->mapWithKeys(fn ($d) => [$d['code'] => Department::updateOrCreate(['code' => $d['code']], ['name' => $d['name']])]);

        foreach ([['X', 'X'], ['XI', 'XI'], ['XII', 'XII']] as [$level, $_]) {
            foreach (['IPA', 'IPS', 'TKJ'] as $i => $code) {
                SchoolClass::updateOrCreate(
                    ['academic_year_id' => $year->id, 'name' => "{$level} {$code} ".($i + 1)],
                    ['department_id' => $departments[$code]->id, 'level' => $level, 'capacity' => 36]
                );
            }
        }

        foreach ([
            ['Foto Siswa', 'foto-3x4', 'Pas foto berwarna 3x4', true, 10],
            ['Kartu Keluarga', 'kk', 'Foto halaman 1 kartu keluarga', true, 20],
            ['Akta Kelahiran', 'akta-kelahiran', 'Akta kelahiran bonne fide', true, 30],
            ['Ijazah / SKL', 'ijazah', 'Dokumen ijazah terakhir atau Surat Keterangan Lulus', true, 40],
            ['KTP Orang Tua', 'ktp-ortu', 'KTP ayah dan ibu atau wali', true, 50],
            ['Dokumen Pendukung', 'pendukung', 'Dokumen tambahan bila diperlukan', false, 60],
        ] as [$name, $slug, $desc, $required, $order]) {
            DocumentType::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'label' => $name,
                    'description' => $desc,
                    'accepted_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
                    'max_size_kb' => 2048,
                    'is_required' => $required,
                    'is_active' => true,
                    'sort_order' => $order,
                ]
            );
        }
    }
}
