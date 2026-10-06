@extends('components.app-shell')

@section('title', 'Data Pendaftaran')
@section('page-title', 'Data Pendaftaran')
@section('page-description', 'Tahun ajaran '.($registration->academicYear?->name ?? '-'))

@section('content')

@php
    $keys = array_keys($steps);
    $currentIndex = array_search($step, $keys, true);
    $isLocked = ! $isEditable;
@endphp

{{-- Locked while an admin is reviewing --}}
@if ($isLocked)
    <x-alert variant="info" class="mb-5"
             title="Pendaftaran sedang diverifikasi"
             message="Anda masih dapat melihat data, tetapi isian dikunci sampai pemeriksaan admin selesai." />
@endif

@if ($rejectedDocuments->isNotEmpty())
    <x-alert variant="error" class="mb-5" title="Ada dokumen yang perlu diperbaiki">
        <ul class="mt-2 space-y-1">
            @foreach ($rejectedDocuments as $doc)
                <li><strong>{{ $doc->documentType?->name }}</strong> — {{ $doc->rejection_reason }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

{{-- ============ Stepper ============ --}}
<nav class="mb-5" aria-label="Tahapan pendaftaran">
    {{-- Desktop: horizontal stepper --}}
    <ol class="hidden md:flex items-center gap-1">
        @foreach ($keys as $i => $key)
            @php
                $done = $i < $currentIndex;
                $active = $i === $currentIndex;
            @endphp
            <li class="flex items-center gap-1 {{ $i < count($keys) - 1 ? 'flex-1' : '' }}">
                <a href="{{ route('siswa.wizard', ['step' => $key]) }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-[var(--radius-md)] transition-colors min-w-0
                          {{ $active ? 'bg-brand-50' : 'hover:bg-[var(--app-surface-muted)]' }}">
                    <span @class([
                        'shrink-0 grid place-items-center w-7 h-7 rounded-full text-caption font-bold transition-colors',
                        'bg-[var(--app-success)] text-white' => $done,
                        'bg-brand-600 text-white ring-4 ring-brand-100' => $active,
                        'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' => ! $done && ! $active,
                    ])>
                        @if ($done)
                            <x-icon name="check" class="w-3.5 h-3.5" />
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span @class([
                        'text-small font-medium truncate',
                        'text-brand-700' => $active,
                        'text-[var(--app-text)]' => $done,
                        'text-[var(--app-text-muted)]' => ! $done && ! $active,
                    ])>{{ $steps[$key]['label'] }}</span>
                </a>
                @if ($i < count($keys) - 1)
                    <span class="h-px flex-1 {{ $done ? 'bg-[var(--app-success)]' : 'bg-[var(--app-border-strong)]' }}"></span>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Mobile: compact progress + current step --}}
    <div class="md:hidden">
        <div class="flex items-center justify-between mb-2">
            <p class="text-small font-semibold text-[var(--app-text)]">{{ $steps[$step]['label'] }}</p>
            <p class="text-caption text-[var(--app-text-muted)]">Langkah {{ $currentIndex + 1 }} dari {{ count($keys) }}</p>
        </div>
        <div class="progress-track">
            <div class="progress-fill" style="width: {{ round(($currentIndex + 1) / count($keys) * 100) }}%"></div>
        </div>
        <div class="mt-2.5 flex gap-1.5 overflow-x-auto no-scrollbar">
            @foreach ($keys as $i => $key)
                <a href="{{ route('siswa.wizard', ['step' => $key]) }}"
                   class="shrink-0 px-2.5 py-1 rounded-full text-caption font-medium transition-colors
                          {{ $i === $currentIndex ? 'bg-brand-600 text-white'
                             : ($i < $currentIndex ? 'bg-[var(--app-success-soft)] text-[var(--app-success)]'
                             : 'bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]') }}">
                    {{ $i + 1 }}. {{ $steps[$key]['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>

<div class="grid lg:grid-cols-4 gap-4 sm:gap-5">
    <div class="lg:col-span-3">

        @if ($errors->any() && $step !== 'review')
            <x-alert variant="error" class="mb-4" title="Ada isian yang perlu diperbaiki"
                     message="{{ $errors->count() }} isian belum tepat. Perhatikan pesan di bawah masing-masing kolom." />
        @endif

        {{-- ============ FORM WRAPPER (posts one step at a time) ============ --}}
        {{-- Only the data steps post here. `dokumen` owns per-document upload
             forms and `review` owns the submit form, and HTML forbids nesting a
             form inside a form: a browser silently discards the inner <form>
             tags, so their inputs fall through to THIS form. That is why
             uploading a document from the wizard discarded the file while
             reporting success, and why "Kirim untuk verifikasi" could never
             succeed — it posted step=review here, whose rules are ['*'], so
             all fifteen biodata fields failed validation on a display-only
             page. --}}
        @php
            $postsToWizard = in_array($step, ['akun', 'pribadi', 'orang-tua', 'pendidikan'], true);
        @endphp
        @if ($postsToWizard)
        <form method="POST" action="{{ route('siswa.wizard.save') }}" novalidate
              x-data="{ dirty: false }"
              @input="dirty = true"
              @change="dirty = true"
              @beforeunload.window="if (dirty) $event.preventDefault()">
            @csrf
            <input type="hidden" name="step" value="{{ $step }}">
        @endif

            {{-- ---------- STEP 1: AKUN ---------- --}}
            @if ($step === 'akun')
                <x-card title="Data Akun" icon="user" description="Digunakan untuk masuk ke aplikasi">
                    <div class="space-y-4">
                        <x-form-field name="name" label="Nama lengkap" required
                                      :value="$student->user?->name" autocomplete="name" />
                        <x-form-field name="email" type="email" label="Email" required
                                      :value="$student->user?->email" autocomplete="email"
                                      hint="Hubungi admin sekolah bila ingin mengubah email." />
                    </div>
                </x-card>
            @endif

            {{-- ---------- STEP 2: DATA PRIBADI ---------- --}}
            @if ($step === 'pribadi')
                <x-card title="Data Pribadi" icon="user" description="Sesuai akta kelahiran">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-form-field name="full_name" label="Nama Lengkap" required :value="$student->full_name" />
                        <x-form-field name="nisn" label="NISN" required maxlength="10" inputmode="numeric"
                                      :value="$student->nisn" />
                        <x-form-field name="nik" label="NIK" maxlength="16" minlength="16" pattern="[0-9]{16}" inputmode="numeric" :value="$student->nik" />
                        <x-form-field name="gender" type="select" label="Jenis Kelamin" required placeholder="-- Pilih --">
                            <option value="L" @selected(old('gender', $student->gender) === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('gender', $student->gender) === 'P')>Perempuan</option>
                        </x-form-field>
                        <x-form-field name="birth_place" label="Tempat Lahir" required :value="$student->birth_place" />
                        <x-form-field name="birth_date" type="date" label="Tanggal Lahir" required
                                      :value="$student->birth_date?->format('Y-m-d')" />
                        <x-form-field name="religion" type="select" label="Agama" required placeholder="-- Pilih --">
                            @foreach ($religions as $r)
                                <option value="{{ $r }}" @selected(old('religion', $student->religion) === $r)>{{ $r }}</option>
                            @endforeach
                        </x-form-field>
                        <x-form-field name="phone" type="tel" label="Nomor HP" required :value="$student->phone"
                                      placeholder="08xxxxxxxxxx" />
                        <x-form-field name="postal_code" label="Kode Pos" maxlength="10" :value="$student->postal_code" />
                        <x-form-field name="address" type="textarea" label="Alamat Lengkap" required :rows="2"
                                      class="sm:col-span-2" :value="$student->address" />
                        <x-form-field name="village" label="Desa / Kelurahan" :value="$student->village" />
                        <x-form-field name="district" label="Kecamatan" :value="$student->district" />
                        <x-form-field name="city" label="Kota / Kabupaten" required :value="$student->city" />
                        <x-form-field name="province" label="Provinsi" :value="$student->province" />
                    </div>
                </x-card>
            @endif

            {{-- ---------- STEP 3: ORANG TUA / WALI ---------- --}}
            @if ($step === 'orang-tua')
                <div class="space-y-4 sm:space-y-5">
                    @foreach ([['father', 'Ayah', 'father_'], ['mother', 'Ibu', 'mother_']] as [$key, $label, $p])
                        <x-card :title="'Data '.$label" icon="users">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-form-field :name="$p.'name'" :label="'Nama Lengkap '.$label" required
                                              :value="$parents[$key]->full_name ?? null" />
                                <x-form-field :name="$p.'job'" label="Pekerjaan"
                                              :value="$parents[$key]->job ?? null" />
                                <x-form-field :name="$p.'phone'" label="Nomor Telepon" required
                                              :value="$parents[$key]->phone ?? null" />
                                <x-form-field :name="$p.'nik'" label="NIK" maxlength="16" inputmode="numeric"
                                              :value="$parents[$key]->nik ?? null" />
                            </div>
                        </x-card>
                    @endforeach

                    <x-card title="Data Wali" icon="user" description="Opsional — isi bila didampingi wali">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-form-field name="guardian_name" label="Nama Lengkap Wali"
                                          :value="$parents['guardian']->full_name ?? null" />
                            <x-form-field name="guardian_job" label="Pekerjaan Wali"
                                          :value="$parents['guardian']->job ?? null" />
                            <x-form-field name="guardian_phone" label="Nomor Telepon Wali"
                                          :value="$parents['guardian']->phone ?? null" />
                            <x-form-field name="parent_address" type="textarea" label="Alamat Orang Tua / Wali"
                                          :rows="2" class="sm:col-span-2"
                                          :value="$parents['father']->address ?? null" />
                        </div>
                    </x-card>
                </div>
            @endif

            {{-- ---------- STEP 4: PENDIDIKAN ---------- --}}
            @if ($step === 'pendidikan')
                <x-card title="Data Pendidikan" icon="book" description="Riwayat sekolah sebelumnya">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-form-field name="previous_school" label="Asal Sekolah" required
                                      :value="$student->previous_school" />
                        <x-form-field name="graduation_year" label="Tahun Lulus" required maxlength="4"
                                      inputmode="numeric" :value="$student->graduation_year" />
                        <x-form-field name="diploma_number" label="Nomor Ijazah / SKL"
                                      :value="$student->diploma_number" />
                        <x-form-field name="previous_score" type="number" label="Nilai Rata-rata" step="0.01"
                                      min="0" max="100" :value="$student->previous_score" />
                    </div>
                </x-card>
            @endif

            {{-- ---------- STEP 5: DOKUMEN (own upload forms) ---------- --}}
            @if ($step === 'dokumen')
                <div class="space-y-4">
                    <x-alert variant="info" title="Panduan unggah"
                             message="Format JPG, PNG, atau PDF maksimal 2 MB per berkas. Pastikan tulisan terbaca jelas." />

                    @foreach ($types as $type)
                        @php $doc = $documents[$type->id] ?? null; @endphp
                        <x-card :title="$type->label">
                            <x-slot:actions>
                                <x-status-badge :status="$doc?->status ?? 'missing'" />
                            </x-slot:actions>

                            <p class="text-small text-[var(--app-text-muted)] mb-4">{{ $type->description }}</p>

                            @if ($doc && $doc->status === 'rejected')
                                <div class="mb-4 rounded-[var(--radius-md)] bg-[var(--app-danger-soft)] border border-[var(--app-danger)]/25 px-3.5 py-3">
                                    <p class="text-caption font-semibold text-[var(--app-danger)] mb-0.5">Alasan ditolak</p>
                                    <p class="text-small text-[var(--app-text)]">{{ $doc->rejection_reason }}</p>
                                </div>
                            @endif

                            @if ($doc && $doc->status !== 'missing')
                                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] px-3.5 py-3">
                                    <x-icon name="file-text" class="w-5 h-5 text-[var(--app-text-muted)] shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-small font-medium text-[var(--app-text)] truncate">{{ $doc->original_name }}</p>
                                        <p class="text-caption text-[var(--app-text-muted)]">
                                            {{ $doc->size_kb }} KB ·
                                            diunggah {{ $doc->uploaded_at?->translatedFormat('d M Y H:i') }}
                                        </p>
                                    </div>
                                    <a href="{{ $doc->url() }}" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-secondary shrink-0">
                                        <x-icon name="eye" class="w-3.5 h-3.5" /> Lihat
                                    </a>
                                </div>
                            @endif

                            @if ($isEditable)
                                <form method="POST" action="{{ route('siswa.documents.upload', $type) }}"
                                      enctype="multipart/form-data"
                                      x-data="{ file: null, name: '' }"
                                      class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    @csrf
                                    <label class="flex-1 min-w-0 cursor-pointer">
                                        <span class="sr-only">Berkas {{ $type->label }}</span>
                                        <input type="file" name="file" required
                                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                                               @change="file = $event.target.files[0] || null; name = file ? file.name : ''"
                                               class="block w-full text-caption text-[var(--app-text-muted)]
                                                      file:mr-3 file:rounded-[var(--radius-md)] file:border-0
                                                      file:bg-[var(--app-surface-muted)] file:px-3 file:py-2
                                                      file:text-caption file:font-semibold file:text-[var(--app-text)]
                                                      file:cursor-pointer hover:file:bg-ink-200">
                                    </label>
                                    <button type="submit" class="btn btn-primary shrink-0 justify-center">
                                        <x-icon name="upload" class="w-4 h-4" />
                                        {{ $doc && $doc->status !== 'missing' ? 'Ganti' : 'Unggah' }}
                                    </button>
                                </form>
                            @else
                                <p class="text-caption text-[var(--app-text-muted)]">
                                    Unggahan dikunci selama verifikasi berlangsung.
                                </p>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif

            {{-- ---------- STEP 6: REVIEW & SUBMIT ---------- --}}
            @if ($step === 'review')
                @php
                    $requiredTypes = $types->where('is_required', true);
                    $uploaded = $requiredTypes->filter(fn ($t) => ($documents[$t->id] ?? null) && $documents[$t->id]->status !== 'missing');
                    $ready = $parents->has('father') && $parents->has('mother') && $uploaded->count() === $requiredTypes->count();
                @endphp

                @if (! $ready)
                    <x-alert variant="warning" class="mb-4" title="Belum bisa dikirim"
                             message="Pastikan data orang tua lengkap dan seluruh dokumen wajib sudah diunggah." />
                @endif

                <div class="space-y-4 sm:space-y-5">
                    <x-card title="Data Pribadi" icon="user">
                        <x-slot:actions>
                            <a href="{{ route('siswa.wizard', ['step' => 'pribadi']) }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Ubah</a>
                        </x-slot:actions>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-small">
                            @foreach ([
                                'NISN' => $student->nisn, 'NIK' => $student->nik ?: '—',
                                'Nama Lengkap' => $student->full_name, 'Jenis Kelamin' => $student->genderLabel(),
                                'Tempat, Tgl Lahir' => trim(($student->birth_place ?? '-').', '.($student->birth_date?->format('d M Y') ?? '-'), ', '),
                                'Agama' => $student->religion, 'Nomor HP' => $student->phone,
                                'Alamat' => $student->address, 'Kota' => $student->city,
                            ] as $k => $v)
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-[var(--app-text-muted)]">{{ $k }}</dt>
                                    <dd class="font-medium text-[var(--app-text)] sm:mt-0.5">{{ $v ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>

                    <x-card title="Orang Tua / Wali" icon="users">
                        <x-slot:actions>
                            <a href="{{ route('siswa.wizard', ['step' => 'orang-tua']) }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Ubah</a>
                        </x-slot:actions>
                        <dl class="space-y-2.5 text-small">
                            @foreach ($parents as $p)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[var(--app-text-muted)]">{{ $p->relationLabel() }}</dt>
                                    <dd class="font-medium text-right">{{ $p->full_name }}
                                        <span class="block text-caption text-[var(--app-text-muted)]">{{ $p->phone }}</span>
                                    </dd>
                                </div>
                            @endforeach
                            @if ($parents->isEmpty())
                                <p class="text-[var(--app-text-subtle)]">Belum diisi.</p>
                            @endif
                        </dl>
                    </x-card>

                    <x-card title="Pendidikan" icon="book">
                        <x-slot:actions>
                            <a href="{{ route('siswa.wizard', ['step' => 'pendidikan']) }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Ubah</a>
                        </x-slot:actions>
                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-small">
                            @foreach ([
                                'Asal Sekolah' => $student->previous_school, 'Tahun Lulus' => $student->graduation_year,
                                'Nomor Ijazah' => $student->diploma_number ?: '—', 'Nilai Rata-rata' => $student->previous_score ?: '—',
                            ] as $k => $v)
                                <div class="flex justify-between gap-3 sm:block">
                                    <dt class="text-[var(--app-text-muted)]">{{ $k }}</dt>
                                    <dd class="font-medium text-[var(--app-text)] sm:mt-0.5">{{ $v ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>

                    <x-card title="Dokumen" icon="files">
                        <x-slot:actions>
                            <a href="{{ route('siswa.wizard', ['step' => 'dokumen']) }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Ubah</a>
                        </x-slot:actions>
                        <ul class="space-y-2.5">
                            @foreach ($types as $type)
                                @php $doc = $documents[$type->id] ?? null; @endphp
                                <li class="flex items-center justify-between gap-3 text-small">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <x-icon name="file-text" class="w-4 h-4 text-[var(--app-text-muted)] shrink-0" />
                                        <span class="truncate">{{ $type->label }}</span>
                                        @unless ($type->is_required)
                                            <span class="text-caption text-[var(--app-text-subtle)]">(opsional)</span>
                                        @endunless
                                    </span>
                                    <x-status-badge :status="$doc?->status ?? 'missing'" class="shrink-0" />
                                </li>
                            @endforeach
                        </ul>
                    </x-card>

                    {{-- Submit lives outside this form: it is a different action. --}}
                    <x-card title="Kirim Pendaftaran" icon="send">
                        <p class="text-body text-[var(--app-text-muted)] mb-4">
                            Setelah dikirim, data Anda akan diperiksa admin. Anda masih dapat memperbaiki dokumen
                            bila diminta perbaikan.
                        </p>

                        <div class="rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] p-4 mb-4">
                            <div class="flex items-center justify-between text-small mb-2">
                                <span class="text-[var(--app-text-muted)]">Kelengkapan data</span>
                                <span class="font-bold tabular-nums">{{ $completeness }}%</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: {{ max(2, $completeness) }}%"></div>
                            </div>
                        </div>

                        @if (in_array($registration->status, [\App\Models\Registration::STATUS_PENDING, \App\Models\Registration::STATUS_SUBMITTED], true))
                            <x-alert variant="info" title="Pendaftaran sudah dikirim"
                                     message="Menunggu pemeriksaan admin. Anda tidak perlu melakukan apa-apa untuk saat ini." />
                        @elseif ($registration->status === \App\Models\Registration::STATUS_VERIFIED)
                            <x-alert variant="success" title="Pendaftaran terverifikasi"
                                     message="Seluruh data Anda telah disetujui." />
                        @else
                            <form method="POST" action="{{ route('siswa.submit') }}"
                                  onsubmit="return confirm('Kirim pendaftaran untuk diverifikasi?')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-lg w-full sm:w-auto justify-center"
                                        @disabled(! $ready)>
                                    <x-icon name="upload" class="w-4 h-4" />
                                    Kirim untuk diverifikasi
                                </button>
                                @unless ($ready)
                                    <p class="mt-2 text-caption text-[var(--app-danger)]">
                                        Lengkapi data orang tua dan dokumen wajib terlebih dahulu.
                                    </p>
                                @endunless
                            </form>
                        @endif
                    </x-card>
                </div>
            @endif

            {{-- ============ Navigation ============ --}}
            @if ($step !== 'review' && $step !== 'dokumen')
                <div class="mt-5 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2">
                    @if ($currentIndex > 0)
                        <a href="{{ route('siswa.wizard', ['step' => $keys[$currentIndex - 1]]) }}"
                           class="btn btn-secondary justify-center sm:justify-start">
                            <x-icon name="chevron-left" class="w-4 h-4" />
                            Sebelumnya
                        </a>
                    @else
                        <a href="{{ route('siswa.dashboard') }}" class="btn btn-ghost justify-center sm:justify-start">
                            <x-icon name="arrow-left" class="w-4 h-4" />
                            Dashboard
                        </a>
                    @endif

                    <div class="flex gap-2">
                        <button type="submit" name="save_only" value="1"
                                class="btn btn-secondary flex-1 sm:flex-none justify-center"
                                @disabled($isLocked)>
                            Simpan
                        </button>
                        <button type="submit"
                                class="btn btn-primary flex-1 sm:flex-none justify-center"
                                @disabled($isLocked)>
                            {{ $currentIndex === count($keys) - 1 ? 'Tinjau' : 'Berikutnya' }}
                            <x-icon name="chevron-right" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @elseif ($step === 'dokumen')
                <div class="mt-5 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2">
                    <a href="{{ route('siswa.wizard', ['step' => $keys[$currentIndex - 1]]) }}"
                       class="btn btn-secondary justify-center sm:justify-start">
                        <x-icon name="chevron-left" class="w-4 h-4" />
                        Sebelumnya
                    </a>
                    <a href="{{ route('siswa.wizard', ['step' => 'review']) }}"
                       class="btn btn-primary justify-center">
                        Lanjut ke review
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </a>
                </div>
            @endif
        @if ($postsToWizard)
        </form>
        @endif
    </div>

    {{-- ============ Side rail ============ --}}
    <div class="lg:col-span-1">
        <x-card title="Kelengkapan" icon="chart">
            <p class="text-3xl font-bold tabular-nums {{ $completeness >= 100 ? 'text-[var(--app-success)]' : 'text-[var(--app-primary)]' }}">
                {{ $completeness }}<span class="text-lg">%</span>
            </p>
            <div class="progress-track mt-3">
                <div class="progress-fill" style="width: {{ max(2, $completeness) }}%"></div>
            </div>

            <ul class="mt-4 space-y-2 text-small">
                @foreach ([
                    'Data pribadi' => filled($student->birth_date) && filled($student->city),
                    'Orang tua' => $parents->has('father') && $parents->has('mother'),
                    'Pendidikan' => filled($student->previous_school),
                    'Dokumen wajib' => $types->where('is_required', true)->filter(
                        fn ($t) => ($documents[$t->id] ?? null) && $documents[$t->id]->status !== 'missing'
                    )->count() === $types->where('is_required', true)->count(),
                ] as $label => $done)
                    <li class="flex items-center gap-2">
                        <x-icon :name="$done ? 'check-circle' : 'clock'" class="w-4 h-4 shrink-0"
                                :class="$done ? 'text-[var(--app-success)]' : 'text-[var(--app-text-subtle)]'" />
                        <span @class(['flex-1', $done ? 'text-[var(--app-text)]' : 'text-[var(--app-text-muted)]'])>{{ $label }}</span>
                        <span @class(['text-caption font-semibold', $done ? 'text-[var(--app-success)]' : 'text-[var(--app-text-subtle)]'])>
                            {{ $done ? 'Selesai' : 'Belum' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Status Pendaftaran" icon="clipboard-check" class="mt-4 sm:mt-5">
            <x-status-badge :status="$registration->status" />
            <p class="mt-3 text-caption text-[var(--app-text-muted)]">
                Terakhir diperbarui {{ $registration->updated_at?->translatedFormat('d M Y H:i') }}
            </p>
        </x-card>
    </div>
</div>
@endsection
