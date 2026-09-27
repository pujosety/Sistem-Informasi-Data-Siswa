@props([
    'title' => 'Konfirmasi tindakan',
    'message' => 'Tindakan ini tidak dapat dibatalkan.',
    'confirmLabel' => 'Ya, lanjutkan',
    'cancelLabel' => 'Batal',
    'form' => null,          // id of a <form> to submit on confirm
    'variant' => 'danger',   // danger | warning
])

{{--
    Reusable confirmation. Two ways to use it:

    1. Submit an existing form by id:
       <x-confirm-dialog form="hapus-doc-{{ $doc->id }}" message="…" />
       <button @click="$dispatch('confirm-open', 'hapus-doc-{{ $doc->id }}')">Hapus</button>

    2. Fire a JS callback (for non-form actions):
       <button @click="$dispatch('confirm-open', 'hapus', () => remove(1))">…</button>
--}}

<div class="fixed z-[70] inset-0" role="dialog" aria-modal="true"
     x-data="{ open: false, target: null, callback: null }"
     x-show="open" x-cloak
     @keydown.escape.window="if (open && ! confirmArmed) { open = false }"
     @confirm-open.window="open = true; target = $event.detail?.form ?? null; callback = ($event.detail?.form ? null : $event.detail) ?? null; $nextTick(() => $refs.confirm.focus())"
     @confirm-cancel.window="open = false">

    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"
         @click="open = false"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div x-show="open"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             transition:enter="transition duration-150 ease-out"
             role="document"
             class="relative w-full max-w-md surface shadow-[var(--shadow-overlay)]">
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span @class([
                        'shrink-0 grid place-items-center w-10 h-10 rounded-[var(--radius-md)]',
                        'bg-[var(--app-danger-soft)] text-[var(--app-danger)]' => $variant === 'danger',
                        'bg-[var(--app-warning-soft)] text-[oklch(0.48_0.12_70)]' => $variant !== 'danger',
                    ])>
                        <x-icon name="alert-triangle" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-h2 font-bold text-[var(--app-text)]">{{ $title }}</h2>
                        <p class="mt-1 text-body text-[var(--app-text-muted)]">{{ $message }}</p>
                        {{ $slot }}
                    </div>
                </div>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 px-5 sm:px-6 py-4
                        border-t border-[var(--app-border)] bg-[var(--app-surface-muted)] rounded-b-[var(--radius-lg)]">
                <button type="button" @click="open = false" class="btn btn-secondary justify-center">
                    {{ $cancelLabel }}
                </button>
                <button type="button" x-ref="confirm"
                        @click="if (target) { document.getElementById(target)?.requestSubmit() } else if (callback) { callback() }; open = false"
                        @class([
                            'btn justify-center',
                            'btn-danger' => $variant === 'danger',
                            'btn-secondary !text-[oklch(0.45_0.11_70)] !bg-[var(--app-warning-soft)]' => $variant !== 'danger',
                        ])>
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
