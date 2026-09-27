@extends('components.app-shell')

@section('title', 'Log Aktivitas')
@section('page-title', 'Log Aktivitas')
@section('page-description', 'Jejak audit perubahan penting')

@section('content')

<form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-48 flex-1">
        <label for="action" class="mb-1 block text-caption font-medium text-[var(--app-text-secondary)]">Aksi</label>
        <select id="action" name="action"
                class="min-h-[44px] w-full rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] px-3 text-small">
            <option value="">Semua aksi</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
    <button class="min-h-[44px] rounded-[var(--radius-md)] bg-[var(--app-surface-alt)] px-3.5 text-small font-semibold">Filter</button>
</form>

{{-- Desktop table. A 5-column audit log at 360px cannot be read, so phones get
     cards with the same facts, stacked. --}}
<div class="hidden overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-sm sm:block">
    <table class="min-w-full divide-y divide-[var(--app-border)] text-small">
        <thead class="bg-[var(--app-surface-alt)] text-left text-caption uppercase tracking-wide text-[var(--app-text-muted)]">
            <tr>
                <th class="px-4 py-3 font-semibold">Waktu</th>
                <th class="px-4 py-3 font-semibold">Pengguna</th>
                <th class="px-4 py-3 font-semibold">Aksi</th>
                <th class="px-4 py-3 font-semibold">Keterangan</th>
                <th class="px-4 py-3 font-semibold">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--app-border)]">
            @forelse ($logs as $log)
                <tr class="hover:bg-[var(--app-surface-alt)]">
                    <td class="px-4 py-2.5 text-caption text-[var(--app-text-muted)] whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2.5">{{ $log->user?->name ?? 'Sistem' }}</td>
                    <td class="px-4 py-2.5"><code class="rounded bg-[var(--app-surface-alt)] px-1.5 py-0.5 text-caption">{{ $log->action }}</code></td>
                    <td class="px-4 py-2.5 text-[var(--app-text-secondary)]">{{ $log->description }}</td>
                    <td class="px-4 py-2.5 text-caption text-[var(--app-text-muted)]">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-small text-[var(--app-text-muted)]">Belum ada aktivitas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="space-y-3 sm:hidden">
    @forelse ($logs as $log)
        <article class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="min-w-0 flex-1 text-small font-semibold">{{ $log->description }}</p>
                <code class="shrink-0 rounded bg-[var(--app-surface-alt)] px-1.5 py-0.5 text-caption">{{ $log->action }}</code>
            </div>
            <p class="mt-1.5 text-caption text-[var(--app-text-muted)]">
                {{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->format('d/m/Y H:i') }}
            </p>
        </article>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-small text-slate-500">Belum ada aktivitas.</p>
    @endforelse
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
