@extends('components.app-shell')

@section('title', 'Izin: '.ucfirst(str_replace('_', ' ', $role->name)))
@section('page-title', 'Hak Akses '.ucfirst(str_replace('_', ' ', $role->name)))
@section('page-description', $role->users_count.' pengguna · '.$grantedCount.' izin aktif')

@section('page-actions')
    <a href="{{ route('admin.roles') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

@php
    $grantedCount = count($granted);
    $allNames = collect($domains)->flatMap(fn ($d) => array_keys($d['permissions']))->all();
    $locked = ! auth()->user()->isSuperAdmin();
@endphp

<form method="POST" action="{{ route('admin.roles.update', $role) }}"
      x-data="permissionMatrix(@js($allNames), @js($granted), @js($locked))"
      class="space-y-4">
    @csrf
    @method('PUT')

    {{-- ---------- Sticky save bar ---------- --}}
    <div class="sticky top-[4.5rem] z-20 -mx-4 sm:mx-0 px-4 sm:px-0 py-3 bg-[var(--app-bg)]/90 backdrop-blur">
        <div class="surface p-3 flex flex-wrap items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-small font-semibold text-[var(--app-text)]">
                    <span x-text="selected.size" x-text="selected.size + ' izin dipilih'">0</span>
                </p>
                <p x-show="dirty" class="text-caption text-[var(--app-warning)] font-medium">
                    Ada perubahan yang belum disimpan.
                </p>
                <p x-show="! dirty" class="text-caption text-[var(--app-text-muted)]">
                    Centik izin yang boleh dilakukan role ini.
                </p>
            </div>
            <button type="button" @click="selectAll()" class="btn btn-sm btn-secondary">Pilih semua</button>
            <button type="button" @click="clearAll()" class="btn btn-sm btn-secondary">Kosongkan</button>
            <button type="submit" class="btn btn-primary" :disabled="! dirty">
                <x-icon name="save" class="w-4 h-4" />
                Simpan Izin
            </button>
        </div>
    </div>

    @if ($locked)
        <x-alert variant="warning" title="Mode terbatas"
                 message="Anda hanya dapat memberikan izin yang Anda miliki sendiri. Izin di luar kewenangan Anda tidak dapat dicentang." />
    @endif

    @foreach ($domains as $key => $domain)
        <x-card :title="$domain['label']" :icon="match($key) {
            'student' => 'users', 'registration' => 'clipboard-check', 'document' => 'files',
            'verification' => 'shield-check', 'report' => 'chart-bar', 'user' => 'user',
            'role' => 'lock', 'settings' => 'settings', 'branding' => 'sparkles',
            'school' => 'school', 'master' => 'database', 'activity' => 'history',
            'system' => 'settings', default => 'layout-dashboard',
        }">
            <x-slot:actions>
                <button type="button" @click="toggleGroup(@js(array_keys($domain['permissions'])))"
                        class="text-small font-semibold text-[var(--app-primary)] hover:underline">
                    Pilih semua / kosongkan
                </button>
            </x-slot:actions>

            {{-- Mobile: accordion so a 45-row matrix is not a wall of checkboxes. --}}
            <div class="sm:hidden">
                <details class="group border border-[var(--app-border)] rounded-[var(--radius-md)] overflow-hidden">
                    <summary class="flex items-center justify-between gap-2 px-3.5 py-3 cursor-pointer list-none bg-[var(--app-surface-muted)]">
                        <span class="text-small font-semibold text-[var(--app-text)]">{{ $domain['label'] }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-caption text-[var(--app-text-muted)]"
                                  x-text="countInGroup(@js(array_keys($domain['permissions']))) + '/' + {{ count($domain['permissions']) }}"></span>
                            <x-icon name="chevron-down" class="w-4 h-4 text-[var(--app-text-muted)] transition-transform group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="divide-y divide-[var(--app-border)]">
                        @foreach ($domain['permissions'] as $name => $label)
                            <label class="flex items-start gap-3 px-3.5 py-3 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $name }}" x-model="selected"
                                       class="checkbox-field rounded mt-0.5 shrink-0"
                                       :disabled="locked && ! mine.includes('{{ $name }}')">
                                <span class="min-w-0">
                                    <span class="block text-small text-[var(--app-text)]">{{ $label }}</span>
                                    <span class="block text-caption font-mono text-[var(--app-text-subtle)]">{{ $name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </details>
            </div>

            {{-- Desktop: grid --}}
            <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach ($domain['permissions'] as $name => $label)
                    @php $danger = str_contains($name, 'delete') || str_contains($name, 'super_admin') || str_contains($name, 'system.update'); @endphp
                    <label @class([
                        'flex items-start gap-2.5 p-3 rounded-[var(--radius-md)] border cursor-pointer transition-colors',
                        'border-[var(--app-danger)]/30 bg-[var(--app-danger-soft)]/40' => $danger && in_array($name, $granted, true),
                        'border-[var(--app-border)] hover:bg-[var(--app-surface-muted)]' => ! ($danger && in_array($name, $granted, true)),
                    ])>
                        <input type="checkbox" name="permissions[]" value="{{ $name }}" x-model="selected"
                               class="checkbox-field rounded mt-0.5 shrink-0"
                               :disabled="locked && ! mine.includes('{{ $name }}')">
                        <span class="min-w-0">
                            <span @class([
                                'block text-small',
                                'text-[var(--app-danger)] font-medium' => $danger,
                                'text-[var(--app-text)]' => ! $danger,
                            ])>{{ $label }}</span>
                            <span class="block text-caption font-mono text-[var(--app-text-subtle)]">{{ $name }}</span>
                            @if ($danger)
                                <span class="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-[var(--app-danger)]">
                                    <x-icon name="alert-triangle" class="w-3 h-3" />
                                    Izin berisiko
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </x-card>
    @endforeach
</form>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('permissionMatrix', (all, granted, locked) => ({
        all,
        mine: @js($assignable),
        locked,
        selected: new Set(granted),
        initial: new Set(granted),

        get dirty() { return this.sizeChanged; },
        get sizeChanged() {
            if (this.selected.size !== this.initial.size) return true;
            for (const v of this.initial) if (!this.selected.has(v)) return true;
            return false;
        },

        toggleGroup(names) {
            const allOn = names.every((n) => this.selected.has(n));
            names.forEach((n) => allOn ? this.selected.delete(n) : this.selected.add(n));
        },

        countInGroup(names) {
            return names.filter((n) => this.selected.has(n)).length;
        },

        selectAll() {
            this.all.forEach((n) => this.selected.add(n));
            this.syncInputs();
        },

        clearAll() {
            this.selected.clear();
            this.syncInputs();
        },

        syncInputs() {
            document.querySelectorAll('input[name="permissions[]"]').forEach((input) => {
                input.checked = this.selected.has(input.value);
            });
        },
    }));

    // x-model on a Set does not write back to the checkbox, so mirror the
    // selection into the DOM whenever it changes.
    document.addEventListener('alpine:init', () => {
        document.addEventListener('change', (e) => {
            const input = e.target.closest('input[name="permissions[]"]');
            if (!input) return;
            const root = input.closest('[x-data^="permissionMatrix"]');
            if (!root) return;
            const data = Alpine.$data(root);
            input.checked ? data.selected.add(input.value) : data.selected.delete(input.value);
        }, true);
    });
});
</script>
@endpush
@endsection
