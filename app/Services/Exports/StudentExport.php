<?php

namespace App\Services\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

    public function map($student): array
    {
        $registration = $student->registration;

        return [
            $student->nisn,
            $student->nik,
            $student->full_name,
            $student->genderLabel(),
            $student->birth_place,
            $student->birth_date?->format('Y-m-d'),
            $student->religion,
            $student->phone,
            $student->address,
            $student->city,
            $student->previous_school,
            $student->graduation_year,
            $student->diploma_number,
            $student->entry_year,
            $student->schoolClass?->name,
            $student->schoolClass?->department?->name,
            $registration?->completeness ?? 0,
            $registration?->statusLabel() ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A8A']]],
        ];
    }
}
