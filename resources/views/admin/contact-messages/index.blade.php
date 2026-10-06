@extends('components.app-shell')

@section('title', 'Pesan masuk')
@section('page-title', 'Pesan masuk')
@section('page-description', $messages->total().' pesan dari pengunjung')

@section('content')
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    <div class="surface overflow-hidden">
        @if ($messages->isEmpty())
            <div class="p-10 text-center">
                <x-icon name="inbox" class="mx-auto size-10 text-[var(--app-text-subtle)]" />
                <p class="mt-3 text-body font-semibold">Belum ada pesan masuk</p>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">Pesan dari formulir kontak publik akan muncul di sini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-small">
                    <thead class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Pengunjung</th>
                            <th class="px-5 py-3 font-semibold">Topik</th>
                            <th class="px-5 py-3 font-semibold">Pesan</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--app-border)]">
                        @foreach ($messages as $message)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold">{{ $message->name }}</p>
                                    <a class="text-caption text-[var(--app-primary)] hover:underline" href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                                    @if ($message->phone)<p class="text-caption text-[var(--app-text-muted)]">{{ $message->phone }}</p>@endif
                                </td>
                                <td class="px-5 py-4 align-top">{{ $message->topic }}</td>
                                <td class="max-w-md px-5 py-4 align-top leading-relaxed text-[var(--app-text-muted)]">{{ $message->message }}</td>
                                <td class="px-5 py-4 align-top">
                                    <span class="inline-flex rounded-full bg-[var(--app-surface-muted)] px-2.5 py-1 text-caption font-semibold">{{ ucfirst($message->status) }}</span>
                                </td>
                                <td class="px-5 py-4 align-top text-right">
                                    <form method="POST" action="{{ route('admin.contact-messages.update', $message) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="status-{{ $message->id }}">Status pesan {{ $message->id }}</label>
                                        <select id="status-{{ $message->id }}" name="status" class="field w-auto py-1.5 text-caption">
                                            @foreach ([\App\Models\ContactMessage::NEW => 'Baru', \App\Models\ContactMessage::READ => 'Dibaca', \App\Models\ContactMessage::RESOLVED => 'Selesai'] as $value => $label)
                                                <option value="{{ $value }}" @selected($message->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-secondary py-1.5 text-caption" type="submit">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--app-border)] px-5 py-4">{{ $messages->links() }}</div>
        @endif
    </div>
@endsection
