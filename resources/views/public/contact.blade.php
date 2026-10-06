@extends('public.layout')

@php
    $title = 'Kontak · ' . ($school['name'] ?: config('branding.platform.name'));
    $errors = session('errors') ?? new \Illuminate\Support\MessageBag();
@endphp
@section('title', $title)
@php $description = 'Kontak dan lokasi ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $description)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Kontak</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Hubungi kami
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
    <div class="grid items-center gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <x-image-placeholder type="contact" aspect="4/3" alt="Guru SMP 1 LYFLA berdiskusi dengan siswa" />
        <div>
            <p class="text-small font-semibold text-[var(--app-primary)]">Yuk, ngobrol.</p>
            <h2 class="mt-2 text-h1 font-bold tracking-tight">Punya pertanyaan? Kami siap membantu.</h2>
            <p class="mt-4 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                Temukan kanal kontak sekolah atau kirim pesan melalui formulir yang tersedia.
            </p>
        </div>
    </div>

    @if (filled($school['address']) || filled($school['city']) || filled($school['email']) || filled($school['phone']))
        <div class="grid gap-6 sm:grid-cols-2">
            @if (filled($school['address']) || filled($school['city']))
                <div class="surface p-6">
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Alamat</p>
                    <address class="mt-2 text-body not-italic leading-relaxed">
                        {{ $school['address'] }}@if (filled($school['city'])),
                            {{ $school['city'] }}@if (filled($school['province'])), {{ $school['province'] }}@endif
                        @endif
                    </address>
                </div>
            @endif

            <div class="surface p-6">
                @if (filled($school['phone']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Telepon</p>
                    {{-- tel: carries only the dialled characters; a formatted
                         number pasted into a URI is a silent failure. --}}
                    <p class="mt-2 text-body">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school['phone']) }}"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['phone'] }}</a>
                    </p>
                @endif

                @if (filled($school['email']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)] mt-5">Email</p>
                    <p class="mt-2 text-body break-words">
                        <a href="mailto:{{ $school['email'] }}"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['email'] }}</a>
                    </p>
                @endif

                @if (filled($school['website']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)] mt-5">Situs web</p>
                    <p class="mt-2 text-body break-words">
                        {{-- A school that typed a bare host gets https added, so
                             the link works; a school that typed a scheme keeps it. --}}
                        @php $href = preg_match('#^https?://#i', $school['website']) ? $school['website'] : 'https://' . $school['website']; @endphp
                        <a href="{{ $href }}" rel="noopener" target="_blank"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['website'] }}</a>
                    </p>
                @endif
            </div>
        </div>

        <div class="mt-10 rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
            <h2 class="text-h3 font-semibold">Tertarik mendaftar?</h2>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Pendaftaran siswa baru dilakukan melalui portal PPDB.
            </p>
            <a href="{{ route('public.admission') }}"
               class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                      bg-[var(--app-primary)] text-white text-body font-semibold
                      hover:opacity-90 transition-opacity focus-visible:outline-2
                      focus-visible:outline-offset-2 focus-visible:outline-[var(--app-primary)]">
                Lihat PPDB
                <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        </div>
    @else
        {{-- Nothing published yet. Better than an empty grid of labels. --}}
        <div class="surface p-8 text-center">
            <p class="text-body font-semibold">Kontak belum dilengkapi</p>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Administrator sekolah dapat mengisinya melalui
                <span class="font-mono text-[13px]">Pengaturan → Sekolah</span>.
            </p>
            <a href="{{ route('public.admission') }}"
               class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                      bg-[var(--app-primary)] text-white text-body font-semibold hover:opacity-90">
                Lihat PPDB
            </a>
        </div>
    @endif

    <section class="mt-12 grid gap-8 lg:grid-cols-[0.85fr_1.15fr]" aria-labelledby="contact-form-title">
        <div>
            <p class="text-small font-semibold text-[var(--public-primary)]">Yuk, ngobrol.</p>
            <h2 id="contact-form-title" class="mt-2 text-h1 font-bold text-[var(--app-text)]">Ada yang ingin ditanyakan?</h2>
            <p class="mt-3 max-w-md text-body leading-relaxed text-[var(--app-text-muted)]">Punya pertanyaan soal sekolah, program, atau pendaftaran? Kirim pesan dan tim sekolah akan menindaklanjutinya.</p>
        </div>

        <form method="POST" action="{{ route('public.contact.submit') }}" class="surface p-6 sm:p-8">
            @csrf
            <div class="absolute -left-[9999px]" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            @if (session('success'))
                <div class="mb-5 rounded-[var(--radius-md)] border border-[var(--app-success)]/30 bg-[var(--app-success-soft)] px-4 py-3 text-small text-[var(--app-success)]" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-[var(--radius-md)] border border-[var(--app-danger)]/30 bg-[var(--app-danger-soft)] px-4 py-3 text-small text-[var(--app-danger)]" role="alert">Periksa kembali isian formulir.</div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="name" label="Nama" required :value="old('name')" autocomplete="name" />
                <x-form-field name="email" type="email" label="Email" required :value="old('email')" autocomplete="email" />
                <x-form-field name="phone" type="tel" label="Nomor WhatsApp" :value="old('phone')" autocomplete="tel" />
                <x-form-field name="topic" type="select" label="Topik" required :value="old('topic')">
                    <option value="">Pilih topik</option>
                    @foreach (['PPDB', 'Program Akademik', 'Kegiatan Sekolah', 'Informasi Umum'] as $topic)
                        <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field name="message" type="textarea" label="Pesan" required :rows="5" class="sm:col-span-2" :value="old('message')" />
            </div>
            <button type="submit" class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-[var(--radius-md)] bg-[var(--public-primary)] px-5 text-body font-semibold text-white transition hover:bg-[var(--app-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--public-primary)]">
                <x-icon name="send" class="size-5" />
                Kirim Pesan
            </button>
        </form>
    </section>
@endsection
