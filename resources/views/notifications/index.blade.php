@extends('components.app-shell')

@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')
@section('page-description', $unreadCount > 0 ? $unreadCount.' notifikasi belum dibaca' : 'Semua notifikasi sudah dibaca')

@section('page-actions')
    @if ($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">
                <x-icon name="check" class="w-4 h-4" />
                Tandai semua dibaca
            </button>
        </form>
    @endif
@endsection

@section('content')
@php
    $toneFor = fn (?string $type) => \App\Services\NotificationService::toneFor($type);
    $destination = fn (array $data) => \App\Services\NotificationService::destinationFor($data, auth()->user());
@endphp

@if ($notifications->isEmpty())
    <x-card>
        <x-empty-state icon="inbox"
                      title="Tidak ada notifikasi"
                      description="Pemberitahuan tentang dokumen, perbaikan, dan hasil verifikasi akan muncul di sini." />
    </x-card>
@else
    {{-- Desktop: list. Mobile: same list, stacked — notifications are short
         enough that a card layout would add nothing. --}}
    <x-card body-class="p-0 sm:p-0" class="overflow-hidden">
        <ul class="divide-y divide-[var(--app-border)]">
            @foreach ($notifications as $n)
                @php
                    $data = $n->data;
                    [$tone, $icon] = $toneFor($data['type'] ?? null);
                    $isUnread = $n->read_at === null;
                @endphp
                <li>
                    <a href="{{ route('notifications.read', $n->id) }}"
                       class="flex items-start gap-3.5 px-4 sm:px-5 py-4 transition-colors hover:bg-[var(--app-surface-muted)] {{ $isUnread ? 'bg-brand-50/40' : '' }}">

                        <span @class([
                            'shrink-0 grid place-items-center w-10 h-10 rounded-[var(--radius-md)]',
                            'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $tone === 'success',
                            'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'   => $tone === 'danger',
                            'bg-[var(--app-info-soft)] text-[var(--app-info)]'       => $tone === 'info',
                        ])>
                            <x-icon :name="$icon" class="w-5 h-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2">
                                <p @class([
                                    'text-body flex-1',
                                    'font-bold text-[var(--app-text)]' => $isUnread,
                                    'font-medium text-[var(--app-text)]' => ! $isUnread,
                                ])>{{ $data['title'] ?? 'Notifikasi' }}</p>
                                @if ($isUnread)
                                    <span class="shrink-0 w-2 h-2 rounded-full bg-[var(--app-primary)] mt-1.5" aria-label="Belum dibaca"></span>
                                @endif
                            </div>

                            <p class="mt-0.5 text-small text-[var(--app-text-muted)]">{{ $data['body'] ?? '' }}</p>

                            <p class="mt-1.5 text-caption text-[var(--app-text-subtle)]">
                                {{ $n->created_at?->diffForHumans() }}
                                @unless ($isUnread)
                                    <span class="text-[var(--app-text-subtle)]">· sudah dibaca</span>
                                @endunless
                            </p>
                        </div>

                        <x-icon name="chevron-right" class="shrink-0 w-4 h-4 text-[var(--app-text-subtle)] mt-3 hidden sm:block" />
                    </a>
                </li>
            @endforeach
        </ul>
    </x-card>

    @if ($notifications->hasPages())
        <div class="mt-5">{{ $notifications->links() }}</div>
    @endif
@endif
@endsection
