@php
    $user = auth()->user();
    $role = $user?->hasRole('admin') ? 'admin' : ($user?->hasRole('kesiswaan') ? 'kesiswaan' : 'siswa');

    $siswaMenu = [
        ['route' => 'siswa.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['route' => 'siswa.biodata', 'label' => 'Biodata Saya', 'icon' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 3.6-6 8-6s8 2 8 6'],
        ['route' => 'siswa.parents', 'label' => 'Data Orang Tua', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.9M9 20H4v-2a4 4 0 013-3.9M12 10a3 3 0 100-6 3 3 0 000 6z'],
        ['route' => 'siswa.documents', 'label' => 'Dokumen', 'icon' => 'M9 12h6m-6 4h6M7 3h7l5 5v13H7z'],
    ];

    $adminMenu = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['route' => 'admin.registrations', 'label' => 'Pendaftaran', 'icon' => 'M9 12h6m-6 4h6M7 3h7l5 5v13H7z'],
        ['route' => 'kesiswaan.students', 'label' => 'Data Siswa', 'icon' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 3.6-6 8-6s8 2 8 6'],
        ['route' => 'admin.users', 'label' => 'Data Pengguna', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21v-1a6 6 0 0112 0v1'],
        ['route' => 'admin.master', 'label' => 'Master Data', 'icon' => 'M3 7h18M3 12h18M3 17h18'],
        ['route' => 'laporan.index', 'label' => 'Laporan', 'icon' => 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
        ['route' => 'admin.activity', 'label' => 'Log Aktivitas', 'icon' => 'M12 8v4l3 2M12 21a9 9 0 100-18 9 9 0 000 18z'],
    ];

    $kesiswaanMenu = [
        ['route' => 'kesiswaan.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['route' => 'kesiswaan.students', 'label' => 'Data Siswa', 'icon' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 3.6-6 8-6s8 2 8 6'],
        ['route' => 'kesiswaan.statistics', 'label' => 'Statistik', 'icon' => 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
        ['route' => 'kesiswaan.rekap', 'label' => 'Rekapitulasi', 'icon' => 'M9 17v-6m4 6V9m4 8v-4'],
        ['route' => 'laporan.index', 'label' => 'Laporan', 'icon' => 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
    ];

    $menu = match ($role) {
        'admin' => $adminMenu,
        'kesiswaan' => $kesiswaanMenu,
        default => $siswaMenu,
    };
@endphp

<aside class="hidden lg:flex w-64 flex-col bg-slate-900 text-slate-300 shrink-0">
    <div class="px-5 py-5 border-b border-slate-800">
        <div class="text-white font-bold text-lg">{{ config('branding.platform.name') }}</div>
        <div class="text-xs text-slate-400 mt-0.5">Sistem Informasi Data Siswa</div>
    </div>
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        @foreach ($menu as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                      {{ request()->routeIs($item['route']) ? 'bg-blue-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
    <div class="p-3 border-t border-slate-800 text-xs text-slate-500">
        {{ ucfirst($role) }} · {{ $user?->name }}
    </div>
</aside>
