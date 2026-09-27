@extends('components.app-shell')

@section('title', 'Log Aktivitas')
@section('heading', 'Log Aktivitas')

@section('content')
<form method="GET" class="card p-4 mb-4 flex gap-2">
    <select class="input" name="action">
        <option value="">Semua aksi</option>
        @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs text-slate-600 border-b">
            <tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3">Aksi</th><th class="px-4 py-3">Keterangan</th><th class="px-4 py-3">IP</th></tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-2 text-xs text-slate-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2">{{ $log->user?->name ?? 'Sistem' }}</td>
                    <td class="px-4 py-2"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $log->action }}</code></td>
                    <td class="px-4 py-2 text-slate-600">{{ $log->description }}</td>
                    <td class="px-4 py-2 text-xs text-slate-400">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada aktivitas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
