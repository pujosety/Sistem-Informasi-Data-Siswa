@php
    $status = $registration?->status ?? 'draft';
@endphp

@if ($status === \App\Models\Registration::STATUS_DRAFT || $status === \App\Models\Registration::STATUS_REVISION)
    <a href="{{ route('siswa.biodata') }}" class="btn btn-secondary w-full justify-center">Lengkapi Biodata</a>
    <a href="{{ route('siswa.parents') }}" class="btn btn-secondary w-full justify-center">Data Orang Tua</a>
    @if ($missing->isNotEmpty())
        <p class="text-xs text-rose-600">Belum diunggah: {{ $missing->pluck('name')->implode(', ') }}</p>
    @endif
    <form method="POST" action="{{ route('siswa.submit') }}">
        @csrf
        <button class="btn btn-primary w-full justify-center" type="submit">Kirim Pendaftaran</button>
    </form>
@elseif ($status === \App\Models\Registration::STATUS_PENDING)
    <p class="text-sm text-amber-700 bg-amber-50 rounded-lg px-3 py-2">Pendaftaran sedang diperiksa admin.</p>
    <a href="{{ route('siswa.documents') }}" class="btn btn-secondary w-full justify-center">Lihat Dokumen</a>
@elseif ($status === \App\Models\Registration::STATUS_VERIFIED)
    <p class="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2">Data Anda sudah terverifikasi.</p>
    <a href="{{ route('siswa.documents') }}" class="btn btn-secondary w-full justify-center">Lihat Dokumen</a>
@else
    <p class="text-sm text-rose-700 bg-rose-50 rounded-lg px-3 py-2">Pendaftaran ditolak. Silakan baca catatan admin.</p>
@endif
