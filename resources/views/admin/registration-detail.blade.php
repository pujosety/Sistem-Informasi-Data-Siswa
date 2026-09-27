@extends('components.app-shell')

@section('title', 'Verifikasi')
@section('page-title', $student->full_name)
@section('page-description', 'NISN '.$student->nisn.' · '.$registration->statusLabel())

@section('page-actions')
    {{-- Queue navigation: prev / position / next --}}
    @if ($queue)
        <div class="flex items-center gap-1">
            @if ($prevStudent)
                <a href="{{ route('admin.registrations.show', $prevStudent) }}"
                   class="btn btn-sm btn-secondary" aria-label="Pendaftar sebelumnya">
                    <x-icon name="chevron-left" class="w-4 h-4" />
                    <span class="hidden sm:inline">Sebelumnya</span>
                </a>
            @else
                <span class="btn btn-sm btn-secondary opacity-40 cursor-not-allowed" aria-disabled="true">
                    <x-icon name="chevron-left" class="w-4 h-4" />
                </span>
            @endif

            <span class="px-2 text-caption text-[var(--app-text-muted)] tabular-nums">
                {{ $queuePosition }} dari {{ $queueTotal }}
            </span>

            @if ($nextStudent)
                <a href="{{ route('admin.registrations.show', $nextStudent) }}"
                   class="btn btn-sm btn-secondary" aria-label="Pendaftar berikutnya">
                    <span class="hidden sm:inline">Berikutnya</span>
                    <x-icon name="chevron-right" class="w-4 h-4" />
                </a>
            @else
                <span class="btn btn-sm btn-secondary opacity-40 cursor-not-allowed" aria-disabled="true">
                    <x-icon name="chevron-right" class="w-4 h-4" />
                </span>
            @endif
        </div>
    @endif
@endsection

@section('content')

@if ($registration->status === \App\Models\Registration::STATUS_VERIFIED)
    <x-alert variant="success" class="mb-4" title="Pendaftaran sudah terverifikasi"
             message="Sudah disetujui oleh {{ $registration->verifier?->name ?? 'admin' }} pada {{ $registration->verified_at?->translatedFormat('d F Y H:i') }}." />
@elseif ($registration->status === \App\Models\Registration::STATUS_REVISION)
    <x-alert variant="warning" class="mb-4" title="Menunggu perbaikan dari siswa"
             message="Siswa sudah diberi tahu dan diminta memperbaiki dokumen." />
@endif

<div class="grid xl:grid-cols-5 gap-4 sm:gap-5">

    {{-- ============ Left: document workspace ============ --}}
    <div class="xl:col-span-3 space-y-4 sm:space-y-5">

        {{-- Tabs: preview vs checklist --}}
        <div x-data="{ tab: @js($activeDocument ? 'preview' : 'checklist') }" class="space-y-4">
            <div class="flex gap-1 p-1 bg-[var(--app-surface-muted)] rounded-[var(--radius-md)] w-fit max-w-full overflow-x-auto no-scrollbar">
                <button type="button" @click="tab = 'checklist'"
                        :class="tab === 'checklist' ? 'bg-[var(--app-surface)] text-[var(--app-text)] shadow-[var(--shadow-card)] font-semibold' : 'text-[var(--app-text-muted)]'"
                        class="px-3.5 py-2 rounded-[var(--radius-sm)] text-small transition-all whitespace-nowrap">
                    Daftar dokumen
                </button>
                <button type="button" @click="tab = 'preview'" @disabled(! $activeDocument)
                        :class="tab === 'preview' ? 'bg-[var(--app-surface)] text-[var(--app-text)] shadow-[var(--shadow-card)] font-semibold' : 'text-[var(--app-text-muted)]'"
                        class="px-3.5 py-2 rounded-[var(--radius-sm)] text-small transition-all disabled:opacity-40 whitespace-nowrap">
                    Pratinjau
                </button>
            </div>

            {{-- ---------- Preview ---------- --}}
            <div x-show="tab === 'preview'" x-cloak>
                @if ($activeDocument)
                    <x-card body-class="p-0 sm:p-0" class="overflow-hidden">
                        <x-slot:actions>
                            <x-status-badge :status="$activeDocument->status" />
                        </x-slot:actions>

                        <div class="p-4 sm:p-5 border-b border-[var(--app-border)] flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-h3 font-semibold text-[var(--app-text)]">{{ $activeDocument->documentType?->name }}</h3>
                                <p class="text-caption text-[var(--app-text-muted)] mt-0.5">
                                    {{ $activeDocument->original_name }} · {{ $activeDocument->size_kb }} KB
                                </p>
                            </div>
                            <a href="{{ $activeDocument->downloadUrl() }}" class="btn btn-sm btn-secondary shrink-0">
                                <x-icon name="download" class="w-4 h-4" />
                                Unduh
                            </a>
                        </div>

                        <div class="bg-[var(--app-surface-muted)] p-3 sm:p-4 min-h-[280px] flex items-center justify-center">
                            @if ($activeDocument->isImage())
                                <img src="{{ $activeDocument->url() }}" alt="Pratinjau {{ $activeDocument->documentType?->name }}"
                                     class="max-w-full max-h-[60vh] rounded-[var(--radius-md)] shadow-[var(--shadow-raised)]"
                                     loading="lazy">
                            @else
                                <iframe src="{{ $activeDocument->url() }}" title="Pratinjau {{ $activeDocument->documentType?->name }}"
                                        class="w-full h-[60vh] min-h-[280px] rounded-[var(--radius-md)] bg-white shadow-[var(--shadow-raised)]"></iframe>
                            @endif
                        </div>
                    </x-card>
                @else
                    <x-card>
                        <x-empty-state icon="eye" title="Pilih dokumen untuk dilihat"
                                      description="Pilih salah satu dokumen pada daftar di samping." />
                    </x-card>
                @endif
            </div>

            {{-- ---------- Checklist + per-document actions ---------- --}}
            <div x-show="tab === 'checklist'">
                <x-card body-class="p-0 sm:p-0" class="overflow-hidden">
                    <ul class="divide-y divide-[var(--app-border)]">
                        @foreach ($documents as $doc)
                            <li class="p-4 sm:p-5">
                                <div class="flex items-start gap-3.5">
                                    <span @class([
                                        'shrink-0 grid place-items-center w-10 h-10 rounded-[var(--radius-md)]',
                                        'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $doc->status === 'valid',
                                        'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'   => $doc->status === 'rejected',
                                        'bg-[var(--app-warning-soft)] text-[oklch(0.5_0.12_70)]' => $doc->status === 'pending',
                                        'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' => $doc->status === 'missing',
                                    ])>
                                        <x-icon name="{{ $doc->status === 'valid' ? 'check-circle' : ($doc->status === 'rejected' ? 'x' : 'file-text') }}"
                                        class="w-5 h-5" />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-body font-semibold text-[var(--app-text)]">{{ $doc->documentType?->name }}</h3>
                                            <x-status-badge :status="$doc->status" />
                                        </div>

                                        <p class="text-caption text-[var(--app-text-muted)] mt-0.5">
                                            {{ $doc->status === 'missing' ? 'Belum diunggah siswa' : $doc->original_name.' · '.$doc->size_kb.' KB' }}
                                        </p>

                                        @if ($doc->rejection_reason)
                                            <p class="mt-2 text-small text-[var(--app-danger)] bg-[var(--app-danger-soft)] rounded-[var(--radius-md)] px-3 py-2">
                                                <strong>Alasan ditolak:</strong> {{ $doc->rejection_reason }}
                                            </p>
                                        @endif

                                        {{-- Actions --}}
                                        @if ($doc->status !== 'missing')
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <a href="{{ $doc->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">
                                                    <x-icon name="eye" class="w-3.5 h-3.5" /> Lihat
                                                </a>
                                                <a href="{{ $doc->downloadUrl() }}" class="btn btn-sm btn-secondary">
                                                    <x-icon name="download" class="w-3.5 h-3.5" /> Unduh
                                                </a>
                                                <button type="button" @click="document.getElementById('reject-{{ $doc->id }}')?.showModal()"
                                                        class="btn btn-sm btn-danger">
                                                    <x-icon name="x" class="w-3.5 h-3.5" /> Minta Perbaikan
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ route('admin.documents.review', [$student, $doc->id]) }}"
                                                  class="mt-3"
                                                  x-data="{ busy: false }"
                                                  @submit="busy = true">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" :disabled="busy"
                                                        x-show="{{ $doc->status === 'valid' ? 'false' : 'true' }}">
                                                    <x-icon name="check" class="w-3.5 h-3.5" />
                                                    <span x-text="busy ? 'Menyimpan…' : 'Setujui dokumen'"></span>
                                                </button>
                                            </form>

                                            {{-- Rejection dialog (replaces native confirm) --}}
                                            <dialog id="reject-{{ $doc->id }}" x-data
                                                    class="w-[min(30rem,calc(100vw-2rem))] p-0 rounded-[var(--radius-lg)] shadow-[var(--shadow-overlay)] backdrop:bg-ink-900/60 backdrop:backdrop-blur-sm">
                                                <form method="POST" action="{{ route('admin.documents.review', [$student, $doc->id]) }}" class="p-5">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <h4 class="text-h3 font-bold text-[var(--app-text)]">Minta perbaikan dokumen</h4>
                                                    <p class="mt-1 text-small text-[var(--app-text-muted)]">
                                                        {{ $doc->documentType?->name }} — siswa akan menerima alasan ini.
                                                    </p>
                                                    <label for="note-{{ $doc->id }}" class="label mt-4">Alasan perbaikan</label>
                                                    <textarea id="note-{{ $doc->id }}" name="note" rows="3" required minlength="10"
                                                              class="field" placeholder="Contoh: Dokumen tidak terbaca dengan jelas. Silakan unggah ulang yang lebih tajam."></textarea>
                                                    <p class="help-text">Minimal 10 karakter agar siswa memahami apa yang harus diperbaiki.</p>
                                                    <div class="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                                                        <button type="button" class="btn btn-secondary justify-center"
                                                                onclick="this.closest('dialog').close()">Batal</button>
                                                        <button type="submit" class="btn btn-danger justify-center">Kirim permintaan</button>
                                                    </div>
                                                </form>
                                            </dialog>
                                        @else
                                            <p class="mt-2 text-caption text-[var(--app-text-subtle)]">
                                                Siswa belum mengunggah dokumen ini.
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>
        </div>
    </div>

    {{-- ============ Right: student context + decision ============ --}}
    <div class="xl:col-span-2 space-y-4 sm:space-y-5">

        {{-- Sticky decision panel on desktop --}}
        <div class="xl:sticky xl:top-24 space-y-4 sm:space-y-5">

            <x-card title="Data Siswa" icon="user">
                <x-slot:actions>
                    @if ($canEditStudent)
                        <button type="button" @click="$dispatch('open-drawer', 'student-edit')" class="text-small font-semibold text-[var(--app-primary)] hover:underline">
                            Ubah
                        </button>
                    @endif
                </x-slot:actions>

                <dl class="grid sm:grid-cols-2 gap-x-5 gap-y-3 text-small">
                    @foreach ([
                        'NISN' => $student->nisn,
                        'NIK' => $student->nik,
                        'Nama' => $student->full_name,
                        'L/P' => $student->genderLabel(),
                        'Tempat Lahir' => $student->birth_place,
                        'Tgl Lahir' => $student->birth_date?->format('d M Y'),
                        'Agama' => $student->religion,
                        'Telepon' => $student->phone,
                        'Asal Sekolah' => $student->previous_school,
                        'Tahun Lulus' => $student->graduation_year,
                        'Kelas' => $student->schoolClass?->name ?? 'Belum ditempatkan',
                        'Angkatan' => $student->entry_year,
                    ] as $k => $v)
                        <div class="flex justify-between gap-3 sm:block">
                            <dt class="text-[var(--app-text-muted)]">{{ $k }}</dt>
                            <dd class="font-medium text-[var(--app-text)] sm:mt-0.5 break-words">{{ $v ?: '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="text-[var(--app-text-muted)]">Alamat</dt>
                        <dd class="font-medium text-[var(--app-text)] mt-0.5">{{ $student->address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Orang Tua / Wali" icon="users">
                @forelse ($student->parents as $p)
                    <div class="flex justify-between gap-3 py-2 first:pt-0 last:pb-0 border-b last:border-0 border-[var(--app-border)]">
                        <span class="text-[var(--app-text-muted)] shrink-0">{{ $p->relationLabel() }}</span>
                        <span class="text-right">
                            <span class="font-medium text-[var(--app-text)] block">{{ $p->full_name }}</span>
                            <span class="text-caption text-[var(--app-text-muted)]">{{ $p->phone }}@if ($p->nik) · {{ $p->nik }}@endif</span>
                        </span>
                    </div>
                @empty
                    <p class="text-small text-[var(--app-text-subtle)]">Data orang tua belum diisi.</p>
                @endforelse
            </x-card>

            {{-- ---------- Decision ---------- --}}
            @if ($registration->status !== \App\Models\Registration::STATUS_VERIFIED)
                <x-card title="Keputusan" icon="gavel" class="border-t-4 border-t-[var(--app-primary)]">
                    <p class="text-small text-[var(--app-text-muted)] mb-4">
                        {{ $outstandingCount > 0
                            ? $outstandingCount.' dokumen belum valid. Setujui akan otomatis memintanya memperbaiki.'
                            : 'Semua dokumen valid. Pendaftaran siap disetujui.' }}
                    </p>

                    <form method="POST" action="{{ route('admin.decide', $student) }}"
                          x-data="{ busy: false }" @submit="busy = true" class="space-y-2">
                        @csrf

                        <label for="admin-note" class="label">Catatan untuk siswa <span class="text-[var(--app-text-subtle)] font-normal">(opsional)</span></label>
                        <textarea id="admin-note" name="note" rows="2" class="field mb-1"
                                  placeholder="Contoh: Data sudah lengkap, terima kasih."></textarea>

                        <button type="submit" name="action" value="approve" class="btn btn-success w-full justify-center"
                                :disabled="busy">
                            <x-icon name="check-circle" class="w-4 h-4" />
                            <span x-text="busy ? 'Memproses…' : 'Setujui Pendaftaran'"></span>
                        </button>

                        <button type="button" @click="document.getElementById('revise-dialog')?.showModal()"
                                class="btn btn-secondary w-full justify-center" :disabled="busy">
                            <x-icon name="rotate-ccw" class="w-4 h-4" />
                            Minta Perbaikan
                        </button>
                    </form>

                    <dialog id="revise-dialog" class="w-[min(30rem,calc(100vw-2rem))] p-0 rounded-[var(--radius-lg)] shadow-[var(--shadow-overlay)] backdrop:bg-ink-900/60 backdrop:backdrop-blur-sm">
                        <form method="POST" action="{{ route('admin.decide', $student) }}" class="p-5"
                              x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <input type="hidden" name="action" value="revise">
                            <h4 class="text-h3 font-bold text-[var(--app-text)]">Minta perbaikan</h4>
                            <p class="mt-1 text-small text-[var(--app-text-muted)]">
                                Pendaftaran kembali ke status perlu perbaikan dan siswa menerima notifikasi.
                            </p>
                            <label for="revise-note" class="label mt-4">Apa yang perlu diperbaiki?</label>
                            <textarea id="revise-note" name="note" rows="3" required minlength="10" class="field"
                                      placeholder="Contoh: Mohon lengkapi data NISN dan unggah kembali ijazah yang lebih jelas."></textarea>
                            <p class="help-text">Minimal 10 karakter.</p>
                            <div class="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                                <button type="button" class="btn btn-secondary justify-center" onclick="this.closest('dialog').close()">Batal</button>
                                <button type="submit" class="btn btn-primary justify-center" :disabled="busy">
                                    <span x-text="busy ? 'Mengirim…' : 'Kirim permintaan'"></span>
                                </button>
                            </div>
                        </form>
                    </dialog>
                </x-card>
            @endif

            {{-- ---------- History ---------- --}}
            <x-card title="Riwayat Verifikasi" icon="history" body-class="p-0 sm:p-0">
                <ul class="divide-y divide-[var(--app-border)]">
                    @forelse ($registration->verifications->sortByDesc('created_at') as $v)
                        <li class="px-4 sm:px-5 py-3.5">
                            <div class="flex items-start gap-2.5">
                                <span @class([
                                    'shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full',
                                    'bg-[var(--app-success)]' => $v->action === 'approve',
                                    'bg-[var(--app-danger)]'  => $v->action === 'reject',
                                    'bg-[oklch(0.62_0.14_75)]' => $v->action === 'revise',
                                    'bg-[var(--app-info)]'    => $v->action === 'submit',
                                ])></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-small font-medium text-[var(--app-text)]">{{ $v->actionLabel() }}</p>
                                    <p class="text-caption text-[var(--app-text-muted)]">
                                        {{ $v->admin?->name ?? 'Siswa' }} · {{ $v->created_at?->translatedFormat('d M Y H:i') }}
                                    </p>
                                    @if ($v->note)
                                        <p class="mt-1 text-caption text-[var(--app-text-muted)] italic">"{{ $v->note }}"</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8">
                            <x-empty-state icon="history" compact title="Belum ada riwayat" />
                        </li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</div>

{{-- ---------- Edit student drawer ---------- --}}
@if ($canEditStudent)
    <div x-data="{ open: false }" @open-drawer.window="if ($event.detail === 'student-edit') open = true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-label="Ubah data siswa">
            <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"></div>
            <div x-show="open" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 transition:enter="transition duration-200 ease-out"
                 class="absolute inset-x-0 bottom-0 max-h-[88vh] overflow-y-auto bg-[var(--app-surface)] rounded-t-[var(--radius-xl)] shadow-[var(--shadow-overlay)]">
                <form method="POST" action="{{ route('admin.registrations.update', $student) }}" class="p-5 sm:p-6">
                    @csrf
                    @method('PUT')
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-h2 font-bold text-[var(--app-text)]">Ubah data siswa</h3>
                        <button type="button" @click="open = false" class="grid place-items-center w-9 h-9 rounded-[var(--radius-md)] hover:bg-[var(--app-surface-muted)]" aria-label="Tutup">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <x-form-field name="full_name" label="Nama lengkap" required :value="$student->full_name" />
                        <x-form-field name="phone" label="Nomor HP" :value="$student->phone" />
                        <x-form-field name="class_id" type="select" label="Kelas" placeholder="Belum ditempatkan">
                            @foreach ($classOptions as $id => $name)
                                <option value="{{ $id }}" @selected($student->class_id === $id)>{{ $name }}</option>
                            @endforeach
                        </x-form-field>
                        <x-form-field name="entry_year" label="Angkatan" maxlength="4" :value="$student->entry_year" />
                    </div>

                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" @click="open = false" class="btn btn-secondary justify-center">Batal</button>
                        <button type="submit" class="btn btn-primary justify-center">Simpan perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
