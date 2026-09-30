@extends('components.app-shell')

@section('title', 'Pengumuman '.$classroom->name)
@section('page-title', 'Pengumuman Kelas')
@section('page-description', $classroom->name.' \u00b7 '.$classroom->academicYear?->name)

@section('page-actions')
    @can('publishAnnouncement', $classroom)
        <a href="{{ route('academic.announcements.create', $classroom) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Pengumuman
        </a>
    @endcan
@endsection

@section('content')

@if ($announcements->isEmpty())
    <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
        <p class="text-sm font-medium text-slate-700">Belum ada pengumuman untuk kelas ini.</p>
        @can('publishAnnouncement', $classroom)
            <a href="{{ route('academic.announcements.create', $classroom) }}"
               class="mt-3 inline-block rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Buat Pengumuman Pertama
            </a>
        @endcan
    </div>
@else
    <div class="space-y-3">
        @foreach ($announcements as $a)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">{{ $a->title }}</h2>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $a->audienceLabel() }}</span>
                        @if ($a->published_at)
                            <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Terbit</span>
                        @else
                            <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Draf</span>
                        @endif
                    </div>
                </div>

                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $a->body }}</p>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>
                        {{ $a->published_at?->format('d M Y H:i') ?? 'Belum dipublikasikan' }}
                        @if ($a->expires_at) \u00b7 berakhir {{ $a->expires_at->format('d M Y') }} @endif
                        @if ($a->author) \u00b7 {{ $a->author->name }} @endif
                    </span>

                    <div class="flex items-center gap-2">
                        @can('editAnnouncement', $classroom)
                            <a href="{{ route('academic.announcements.edit', [$classroom, $a]) }}"
                               class="rounded-md px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Ubah</a>
                        @endcan
                        @can('deleteAnnouncement', $classroom)
                            <form method="POST" action="{{ route('academic.announcements.destroy', [$classroom, $a]) }}"
                                  onsubmit="return confirm('Hapus pengumuman ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-md bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>
@endif
@endsection
