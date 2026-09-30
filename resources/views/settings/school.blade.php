@extends('components.app-shell')

@section('title', 'Profil Sekolah')
@section('page-title', 'Profil Sekolah')
@section('page-description', 'Identitas yang dipakai pada laporan dan PDF')

@section('page-actions')
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-3xl">
    <form method="POST" action="{{ route('settings.school.update') }}" class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @method('PUT')
        {{-- Set once at the top rather than on every field: a form that LOOKS
             editable but is not is the thing this guards against. --}}
        @if (! auth()->user()->can('school.update'))
            <fieldset disabled>
        @endif

        <x-card title="Identitas Utama" icon="school">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="school.name" label="Nama Sekolah" required
                              :value="old('school.name', $values['school.name']['value'] ?? '')"
                              class="sm:col-span-2" />
                <x-form-field name="school.npsn" label="NPSN"
                              :value="old('school.npsn', $values['school.npsn']['value'] ?? '')" />
                <x-form-field name="school.headmaster" label="Nama Kepala Sekolah"
                              :value="old('school.headmaster', $values['school.headmaster']['value'] ?? '')" />
            </div>
        </x-card>

        <x-card title="Alamat" icon="map-pin">
            <div class="space-y-4">
                <x-form-field name="school.address" type="textarea" label="Alamat" :rows="2"
                              :value="old('school.address', $values['school.address']['value'] ?? '')" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-form-field name="school.province" label="Provinsi"
                                  :value="old('school.province', $values['school.province']['value'] ?? '')" />
                    <x-form-field name="school.city" label="Kabupaten / Kota"
                                  :value="old('school.city', $values['school.city']['value'] ?? '')" />
                    <x-form-field name="school.district" label="Kecamatan"
                                  :value="old('school.district', $values['school.district']['value'] ?? '')" />
                    <x-form-field name="school.postal_code" label="Kode Pos" maxlength="10"
                                  :value="old('school.postal_code', $values['school.postal_code']['value'] ?? '')" />
                </div>
            </div>
        </x-card>

        <x-card title="Kontak" icon="phone">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="school.email" type="email" label="Email"
                              :value="old('school.email', $values['school.email']['value'] ?? '')" />
                <x-form-field name="school.phone" label="Nomor Telepon"
                              :value="old('school.phone', $values['school.phone']['value'] ?? '')" />
                <x-form-field name="school.website" label="Website"
                              :value="old('school.website', $values['school.website']['value'] ?? '')"
                              hint="Tanpa https:// — contoh: sekolah.sch.id" />
            </div>
        </x-card>

        <x-card body-class="p-4 sm:p-5" class="bg-brand-50/40 border-brand-200">
            <p class="text-small text-[var(--app-text)]">
                Data ini otomatis dipakai pada <strong>header laporan, PDF, dan pratinjau</strong> —
                tidak perlu diubah di beberapa tempat.
            </p>
        </x-card>

        {{--
            The save button is gated, and the fields are made readonly when it
            is absent.

            The route is guarded on school.update, so an unsaved form for a role
            without it is a form that looks editable and 403s on submit. Showing
            a working-looking button and refusing the POST is worse than saying
            plainly that this is read-only.
        --}}
        @can('school.update')
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="save" class="w-4 h-4" />
                    Simpan Profil Sekolah
                </button>
            </div>
        @else
            <x-alert variant="info"
                     title="Hanya dapat dilihat"
                     message="Anda memiliki izin untuk melihat profil sekolah, tetapi tidak untuk mengubahnya. Hubungi Super Admin." />
        @endcan

        @if (! auth()->user()->can('school.update'))
            </fieldset>
        @endif
    </form>
</div>
@endsection
