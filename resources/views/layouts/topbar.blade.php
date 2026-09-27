<header class="bg-white border-b border-slate-200 px-4 lg:px-6 py-3 flex items-center justify-between">
    <div>
        <h1 class="text-lg font-semibold text-slate-800">@yield('heading', 'Dashboard')</h1>
        <p class="text-xs text-slate-500">@yield('subheading', config('app.name'))</p>
    </div>
    <div class="flex items-center gap-3">
        <span class="hidden sm:inline text-sm text-slate-600">{{ auth()->user()?->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-secondary" type="submit">Keluar</button>
        </form>
    </div>
</header>
