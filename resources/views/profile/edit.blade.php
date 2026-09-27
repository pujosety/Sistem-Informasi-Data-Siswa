@extends('components.app-shell')

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')
@section('page-description', 'Kelola data akun dan keamanan Anda')

@section('content')
<div class="grid lg:grid-cols-3 gap-4 sm:gap-5">
    <div class="lg:col-span-2 space-y-4 sm:space-y-5">

        {{-- Account details --}}
        <x-card title="Data Akun" icon="user">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4" novalidate>
                @csrf
                @method('PUT')

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-form-field name="name" label="Nama lengkap" required
                                  :value="$user->name" autocomplete="name" />
                    <x-form-field name="email" type="email" label="Email" required
                                  :value="$user->email" autocomplete="email"
                                  hint="Dipakai untuk masuk. Hubungi admin bila ingin diubah." />
                </div>

                <x-form-field name="phone" type="tel" label="Nomor HP (opsional)"
                              :value="$user->phone" placeholder="08xxxxxxxxxx"
                              hint="Nomor ajar bila ada emergency." />

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="save" class="w-4 h-4" />
                        Simpan profil
                    </button>
                </div>
            </form>
        </x-card>

        {{-- Password --}}
        <x-card title="Ubah Password" icon="shield-check"
                description="Gunakan password yang kuat dan tidak dipakai di layanan lain.">
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4" novalidate
                  x-data>
                @csrf
                @method('PUT')

                <x-form-field name="current_password" type="password" label="Password saat ini" required
                              autocomplete="current-password" />

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-form-field name="password" type="password" label="Password baru" required
                                  autocomplete="new-password" hint="Minimal 8 karakter." />
                    <x-form-field name="password_confirmation" type="password" label="Ulangi password baru" required
                                  autocomplete="new-password" />
                </div>

                {{-- Live strength meter: password rules are visible, not guessed. --}}
                <div x-data="{ pw: '', get score() { const v = this.pw; let s = 0;
                        if (v.length >= 8) s++;
                        if (v.length >= 12) s++;
                        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
                        if (/\d/.test(v)) s++;
                        if (/[^\w\s]/.test(v)) s++; return s; },
                                              get label() { return ['Sangat lemah','Lemah','Cukup','Kuat','Sangat kuat','Sangat kuat'][this.score]; } }"
                     class="hidden sm:block">
                    <template x-if="pw.length > 0">
                        <div>
                            <div class="flex justify-between text-caption mb-1.5">
                                <span class="text-[var(--app-text-muted)]">Kekuatan password</span>
                                <span class="font-semibold"
                                      :class="{ 'text-[var(--app-danger)]': score <= 1, 'text-[oklch(0.5_0.12_70)]': score === 2, 'text-[var(--app-success)]': score >= 3 }"
                                      x-text="label"></span>
                            </div>
                            <div class="h-1.5 rounded-full bg-[var(--app-surface-muted)] overflow-hidden flex gap-1">
                                <template x-for="i in 5" :key="i">
                                    <div class="flex-1 rounded-full transition-colors"
                                         :class="i <= score ? (score <= 1 ? 'bg-[var(--app-danger)]' : score === 2 ? 'bg-[oklch(0.6_0.13_75)]' : 'bg-[var(--app-success)]') : 'bg-transparent'"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="lock" class="w-4 h-4" />
                        Ubah password
                    </button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- Side rail --}}
    <div class="space-y-4 sm:space-y-5">
        <x-card>
            <div class="flex flex-col items-center text-center">
                <span class="grid place-items-center w-20 h-20 rounded-full bg-brand-100 text-brand-700 text-h1 font-bold">
                    {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                </span>
                <p class="mt-3 text-h3 font-bold text-[var(--app-text)]">{{ $user->name }}</p>
                <p class="text-small text-[var(--app-text-muted)] break-all">{{ $user->email }}</p>

                <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                    @foreach ($user->roles as $role)
                        <span class="badge badge-brand">{{ ucfirst($role->name) }}</span>
                    @endforeach
                </div>
            </div>
        </x-card>

        @if ($user->student)
            <x-card title="Tautan Akun" icon="link">
                <dl class="space-y-2.5 text-small">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)] shrink-0">NISN</dt>
                        <dd class="font-medium">{{ $user->student->nisn }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)] shrink-0">Status</dt>
                        <dd><x-status-badge :status="$user->student->status" /></dd>
                    </div>
                </dl>
                <a href="{{ route('siswa.dashboard') }}" class="btn btn-secondary w-full justify-center mt-4">
                    <x-icon name="arrow-right" class="w-4 h-4" />
                    Ke dashboard siswa
                </a>
            </x-card>
        @endif
    </div>
</div>
@endsection
