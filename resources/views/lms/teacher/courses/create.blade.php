@extends('components.app-shell')

@section('title', 'LMS · Course Baru')
@section('page-title', 'Buat Course')
@section('page-description', 'Course baru dimulai sebagai draft')

@section('content')
<form method="POST" action="{{ route('lms.teacher.courses.store') }}" class="space-y-5 max-w-3xl">
    @csrf
    <x-card>
        <div class="grid gap-4 md:grid-cols-2">
            <x-form-field name="title" label="Nama course" required />
            <x-form-field name="code" label="Kode course" required hint="Contoh: RPL-X-SEM1" />

            <x-form-field name="academic_year_id" label="Tahun ajaran" type="select" required>
                <option value="">Pilih tahun ajaran</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                @endforeach
            </x-form-field>

            <x-form-field name="semester_id" label="Semester" type="select" required>
                <option value="">Pilih semester</option>
                @foreach ($semesters as $semester)
                    <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->academicYear?->name }} · {{ $semester->displayLabel() }}</option>
                @endforeach
            </x-form-field>

            <x-form-field name="subject_id" label="Mata pelajaran" type="select" required>
                <option value="">Pilih mata pelajaran</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </x-form-field>

            <x-form-field name="classroom_id" label="Kelas" type="select" required>
                <option value="">Pilih kelas</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }} · {{ $classroom->academicYear?->name }}</option>
                @endforeach
            </x-form-field>
        </div>
        <x-form-field name="description" label="Deskripsi" type="textarea" class="mt-4" />
    </x-card>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.index') }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Draft</button>
    </div>
</form>
@endsection
