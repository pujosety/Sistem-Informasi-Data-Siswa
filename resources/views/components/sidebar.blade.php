@php
    $role = match (true) {
        auth()->user()?->hasRole('admin') => 'admin',
        auth()->user()?->hasRole('kesiswaan') => 'kesiswaan',
        default => 'siswa',
    };

    $roleMeta = [
        'siswa'     => ['label' => 'Siswa',       'icon' => 'graduation-cap'],
        'kesiswaan' => ['label' => 'Kesiswaan',   'icon' => 'briefcase'],
        'admin'     => ['label' => 'Administrator', 'icon' => 'shield-check'],
    ];

    $roleLabel = $roleMeta[$role]['label'];
    $roleIcon  = $roleMeta[$role]['icon'];

    $pendingBadge = \App\Models\Registration::where('status', \App\Models\Registration::STATUS_PENDING)->count();
@endphp

<div class="flex flex-col h-full">

    {{-- Brand ------------------------------------------------------------- --}}
    <div class="flex items-center gap-2.5 h-16 px-4 shrink-0 border-b border-white/8">
        <span class="grid place-items-center w-8 h-8 rounded-[var(--radius-md)] bg-white/10 shrink-0">
            <x-icon :name="$roleIcon" class="w-[18px] h-[18px] text-white" />
        </span>
        <div class="min-w-0 nav-label">
            <p class="text-body font-bold text-white leading-tight">SIDA</p>
            <p class="text-[11px] text-[var(--app-sidebar-text)] leading-tight truncate">{{ $roleLabel }}</p>
        </div>

        <button type="button" @click="$store.app.toggle()"
                class="ml-auto hidden lg:grid place-items-center w-8 h-8 rounded-[var(--radius-md)] text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/10 transition-colors"
                aria-label="Ciutkan atau perluas navigasi">
            <x-icon name="panel-left" class="w-[18px] h-[18px]" />
        </button>
    </div>

    {{-- Navigation -------------------------------------------------------- --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-0.5 scrollbar-thin" aria-label="Navigasi utama">
        <x-nav-items :items="$navigation" />
    </nav>

    {{-- User ------------------------------------------------------------- --}}
    <div class="p-3 shrink-0 border-t border-white/8">
        <div class="flex items-center gap-2.5 px-2 py-2 rounded-[var(--radius-md)] bg-white/5">
            <span class="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-white/15 text-white text-caption font-bold">
                {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 2)) }}
            </span>
            <div class="min-w-0 flex-1 nav-label">
                <p class="text-small font-semibold text-white truncate">{{ auth()->user()?->name }}</p>
                <p class="text-[11px] text-[var(--app-sidebar-text)] truncate">{{ auth()->user()?->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="nav-label shrink-0">
                @csrf
                <button type="submit" class="grid place-items-center w-8 h-8 rounded-[var(--radius-md)] text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/10 transition-colors"
                        aria-label="Keluar" title="Keluar">
                    <x-icon name="log-out" class="w-[18px] h-[18px]" />
                </button>
            </form>
        </div>
    </div>
</div>
