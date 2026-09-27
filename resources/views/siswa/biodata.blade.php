@extends('components.app-shell')

@section('title', 'Biodata Saya')
@section('page-title', 'Biodata Saya')
@section('page-description', 'Lengkapi data pribadi Anda agar pendaftaran dapat diverifikasi')

@section('content')

@if ($registration && ! $registration->isEditableByStudent())
    <x-alert variant="warning" class="mb-5"
             title="Data sedang diverifikasi"
             message="Pendaftaran Anda sedang diperiksa admin sehingga isian tidak dapat diubah sementara waktu." />
@endif

<form method="POST" action="{{ route('siswa.biodata.update') }}" class="space-y-4 sm:space-y-5" novalidate>
    @csrf
    @method('PUT')

    <x-card title="Data Pribadi" icon="user" description="Sesuai akta kelahiran">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <x-form-field name="nisn" label="NISN" required maxlength="10" inputmode="numeric"
                          :value="$student->nisn" />
            <x-form-field name="nik" label="NIK" maxlength="16" inputmode="numeric"
                          :value="$student->nik" hint="16 digit sesuai KTP." />
            <x-form-field name="full_name" label="Nama Lengkap" required :value="$student->full_name"
                          class="sm:col-span-2 lg:col-span-1" />

            <x-form-field name="gender" type="select" label="Jenis Kelamin" required
                          placeholder="-- Pilih --">
                <option value="L" @selected(old('gender', $student->gender) === 'L')>Laki-laki</option>
                <option value="P" @selected(old('gender', $student->gender) === 'P')>Perempuan</option>
            </x-form-field>

            <x-form-field name="birth_place" label="Tempat Lahir" required :value="$student->birth_place" />
            <x-form-field name="birth_date" type="date" label="Tanggal Lahir" required
                          :value="$student->birth_date?->format('Y-m-d')" />

            <x-form-field name="religion" type="select" label="Agama" required placeholder="-- Pilih --">
                @foreach ($religions as $religion)
                    <option value="{{ $religion }}" @selected(old('religion', $student->religion) === $religion)>{{ $religion }}</option>
                @endforeach
            </x-form-field>

            <x-form-field name="phone" type="tel" label="Nomor HP" required :value="$student->phone"
                          placeholder="08xxxxxxxxxx" />
            <x-form-field name="postal_code" label="Kode Pos" maxlength="10" :value="$student->postal_code" />

            <x-form-field name="address" type="textarea" label="Alamat Lengkap" required :rows="2"
                          class="sm:col-span-2 lg:col-span-3" :value="$student->address" />
            <x-form-field name="village" label="Desa / Kelurahan" :value="$student->village" />
            <x-form-field name="district" label="Kecamatan" :value="$student->district" />
            <x-form-field name="city" label="Kota / Kabupaten" required :value="$student->city" />
            <x-form-field name="province" label="Provinsi" :value="$student->province" />
        </div>
    </x-card>

    <x-card title="Data Pendidikan" icon="book" description="Riwayat sekolah sebelumnya">
        <div class="grid sm:grid-cols-2 gap-4">
            <x-form-field name="previous_school" label="Asal Sekolah" required :value="$student->previous_school" />
            <x-form-field name="graduation_year" label="Tahun Lulus" required maxlength="4"
                          inputmode="numeric" :value="$student->graduation_year" />
            <x-form-field name="diploma_number" label="Nomor Ijazah / SKL" :value="$student->diploma_number" />
            <x-form-field name="previous_score" type="number" label="Nilai Rata-rata" step="0.01"
                          min="0" max="100" :value="$student->previous_score" />
        </div>
    </x-card>

    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
        <a href="{{ route('siswa.dashboard') }}" class="btn btn-secondary justify-center">Batal</a>
        <button type="submit" class="btn btn-primary justify-center" @disabled($registration && ! $registration->isEditableByStudent())>
            <x-icon name="save" class="w-4 h-4" />
            Simpan Biodata
        </button>
    </div>
</form>
@endsection
