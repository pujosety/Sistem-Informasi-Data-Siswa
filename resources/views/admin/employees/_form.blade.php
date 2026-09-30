@php
    /*
     * The employment form, shared by create and edit.

     * WHAT IS NOT IN HERE, AND WHY
     *
     * There is no name, email or phone field. Those live on `users` and only
     * there. A form that offered them would invite an administrator to type a
     * name that quietly disagrees with the account, and the stale copy is what
     * would reach a report card. The account is shown read-only instead.
     *
     * There is no user_id field on edit either, and no resigned_at. The linked
     * account is a re-attribution, not an edit, and the termination date
     * belongs to resign() so an ordinary save cannot rewrite it.
     */
    $isEdit = $employee->exists;
@endphp

<div class="max-w-2xl space-y-4 sm:space-y-5">
    <form method="POST"
          action="{{ $isEdit ? route('admin.employees.update', $employee) : route('admin.employees.store') }}"
          class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-card title="Akun Holders" icon="user"
                description="Identitas dibaca dari akun pengguna, tidak dicadangkan di sini.">
            @if ($isEdit)
                {{-- Fixed: the controller refuses a changed user_id outright. --}}
                <input type="hidden" name="user_id" value="{{ $employee->user_id }}">
                <div class="flex items-center gap-3">
                    <span class="grid place-items-center w-10 h-10 rounded-full bg-brand-50 text-brand-700 font-bold text-sm shrink-0">
                        {{ strtoupper(mb_substr($employee->displayName(), 0, 2)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-[var(--app-text)] truncate">{{ $employee->displayName() }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $employee->user?->email ?? '—' }}</p>
                    </div>
                </div>
                <p class="help-text mt-3">
                    Akun tertaut tidak dapat diganti. Buat catatan baru untuk orang yang berbeda.
                </p>
            @else
                <x-form-field name="user_id" type="select" label="Akun staff" required
                              :value="old('user_id', $selectedUserId)">
                    @foreach ($linkableUsers as $u)
                        <option value="{{ $u->id }}" @selected((int) old('user_id', $selectedUserId) === $u->id)>
                            {{ $u->name }} — {{ $u->email }}
                        </option>
                    @endforeach
                </x-form-field>
                <p class="help-text">
                    Hanya akun yang memegang role staff dan belum punya catatan kepegawaian yang muncul di sini.
                </p>
            @endif
        </x-card>

        <x-card title="Jabatan & Kontrak" icon="briefcase">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="employee_number" label="Nomor induk pegawai"
                              :value="$employee->employee_number" placeholder="Contoh: PG-2026-001"
                              hint="Boleh dikosongkan." />
                <x-form-field name="department_id" type="select" label="Unit kerja"
                              :value="$employee->department_id" placeholder="— Tanpa unit —">
                    @foreach ($departments as $id => $name)
                        <option value="{{ $id }}" @selected((int) old('department_id', $employee->department_id) === $id)>
                            {{ $name }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field name="position" label="Jabatan" :value="$employee->position"
                              placeholder="Contoh: Guru Matematika" />
                <x-form-field name="employment_type" type="select" label="Jenis hubungan kerja" required
                              :value="old('employment_type', $employee->employment_type)" placeholder="false">
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_type', $employee->employment_type) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-form-field>
            </div>
        </x-card>

        <x-card title="Status & Tanggal" icon="calendar">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="employment_status" type="select" label="Status" required
                              :value="old('employment_status', $employee->employment_status)" placeholder="false">
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_status', $employee->employment_status) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field name="hire_date" type="date" label="Tanggal masuk"
                              :value="$employee->hire_date?->format('Y-m-d')" />
                <x-form-field name="contract_start" type="date" label="Mulai kontrak"
                              :value="$employee->contract_start?->format('Y-m-d')" />
                <x-form-field name="contract_end" type="date" label="Berakhir kontrak"
                              :value="$employee->contract_end?->format('Y-m-d')"
                              hint="Kosongkan untuk pegawai tetap." />
                <x-form-field name="work_address" label="Alamat kerja" class="sm:col-span-2"
                              :value="$employee->work_address" />
                <x-form-field name="notes" type="textarea" label="Catatan" class="sm:col-span-2"
                              :value="$employee->notes"
                              placeholder="Catatan internal, mis. riwayat jabatan atau #'inventaris." />
            </div>
        </x-card>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
            <a href="{{ $isEdit ? route('admin.employees.show', $employee) : route('admin.employees') }}"
               class="btn btn-secondary justify-center">Batal</a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-icon name="save" class="w-4 h-4" />
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kepegawaian' }}
            </button>
        </div>
    </form>

    {{--
        Resign / reinstate live OUTSIDE the form, in their own form with their
        own route, so that a stale browser tab cannot submit "save" with a
        termination hidden in the payload — and so the permission can differ
        from employee.update.
    --}}
    @if ($isEdit)
        @if ($employee->isCurrent())
            @can('employee.resign')
                <x-card title="Akhiri Hubungan Kerja" icon="alert-triangle"
                        description="Catatan tetap disimpan; nilai dan tugas yang pernah ia buat tidak ikut terhapus.">
                    {{-- The form carries an id so the confirm dialog can submit
                         it; the visible button only opens the dialog. --}}
                    <form id="resign-{{ $employee->id }}" method="POST"
                          action="{{ route('admin.employees.resign', $employee) }}"
                          class="space-y-3">
                        @csrf
                        <x-form-field name="resignation_reason" label="Alasan" :rows="2"
                                      placeholder="Contoh: Pensiun, pindah tugas, kontrak habis." />
                        <button type="button" class="btn btn-danger"
                                @click="$dispatch('confirm-open', 'resign-{{ $employee->id }}')">
                            <x-icon name="alert-triangle" class="w-4 h-4" />
                            Catat Berhenti
                        </button>
                    </form>

                    <x-confirm-dialog form="resign-{{ $employee->id }}"
                                      title="Akhiri hubungan kerja?"
                                      message="Catatan kepegawaian tetap ada dan bisa diaktifkan kembali, tetapi orang ini tidak lagi dihitung sebagai pegawai aktif."
                                      confirm-label="Ya, catat berhenti" />
                </x-card>
            @endcan
        @else
            @can('employee.reinstate')
                <x-card title="Aktifkan Kembali" icon="rotate-ccw"
                        description="Status dikembalikan ke Aktif. Riwayat berhenti tetap tercatat di log aktivitas.">
                    <form method="POST" action="{{ route('admin.employees.reinstate', $employee) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="rotate-ccw" class="w-4 h-4" />
                            Aktifkan Kembali
                        </button>
                    </form>
                </x-card>
            @endcan
        @endif
    @endif
</div>
