@extends('components.app-shell')

@section('title', 'Modul')
@section('page-title', 'Modul')
@section('page-description', 'Nyalakan atau matikan bagian platform')

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif
@if (session('error'))
    <x-alert variant="danger" class="mb-4" :message="session('error')" />
@endif

{{--
    The honest framing of what a toggle does.

    §50 rules out arbitrary plugin execution, so switching a module OFF hides
    navigation — it does not uninstall anything or revoke a permission. Saying
    so here is what stops an operator from believing they have hardened the
    system by switching a section off, which is exactly the belief that makes
    a dormant setting dangerous.
--}}
<x-alert variant="info" class="mb-4"
         title="Mematikan modul menyembunyikan navigasinya"
         message="Kode dan data modul tetap ada. Izin yang sudah diberikan juga tidak ikut dicabut — untuk menonaktifkan akses sepenuhnya, cabut izinnya lewat Role & Hak Akses." />

<div class="space-y-3">
    @foreach ($modules as $module)
        <x-card>
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-h3 font-semibold text-[var(--app-text)]">{{ $module->name }}</h2>
                        @if ($module->is_required)
                            <span class="badge badge-brand">
                                <x-icon name="lock" class="w-3 h-3" />
                                Wajib
                            </span>
                        @endif
                        <span @class([
                            'badge',
                            'badge-success' => $module->is_enabled,
                            'badge-neutral' => ! $module->is_enabled,
                        ])>
                            {{ $module->is_enabled ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <p class="text-caption text-[var(--app-text-muted)] mt-1">
                        <span class="font-mono">{{ $module->key }}</span>
                        @if ($module->route_prefix)
                            · <span class="font-mono">{{ $module->route_prefix }}</span>
                        @else
                            · tanpa prefix route
                        @endif
                    </p>
                </div>

                <div class="shrink-0">
                    @if ($module->is_required && ! $module->is_enabled)
                        {{-- Unreachable via the service, which refuses. Shown anyway
                             so the state is explained rather than merely disabled. --}}
                        <span class="text-caption text-[var(--app-text-subtle)]">Tidak dapat dimatikan</span>
                    @elseif (! $module->is_enabled && ! $module->canBeDisabled())
                        <span class="text-caption text-[var(--app-text-subtle)]">Tidak dapat dimatikan</span>
                    @elseif (auth()->user()->can('system.update'))
                        {{--
                            The button carries the target state as its value, so
                            there is one field and no ambiguity about what
                            "unchecked" means. A hidden input carrying the
                            current value alongside it would be a second field
                            with the same name, and PHP keeps the last — which
                            is correct only by accident and reads as a bug the
                            first time the markup is reordered.
                        --}}
                        <form method="POST" action="{{ route('admin.modules.update', $module) }}">
                            @csrf
                            @method('PUT')
                            <button class="btn {{ $module->is_enabled ? 'btn-secondary' : 'btn-primary' }}"
                                    name="is_enabled" value="{{ $module->is_enabled ? 0 : 1 }}">
                                <x-icon name="{{ $module->is_enabled ? 'x' : 'check' }}" class="w-4 h-4" />
                                {{ $module->is_enabled ? 'Matikan' : 'Nyalakan' }}
                            </button>
                        </form>
                    @else
                        <span class="text-caption text-[var(--app-text-subtle)]">Tanpa izin</span>
                    @endif
                </div>
            </div>
        </x-card>
    @endforeach
</div>
@endsection
