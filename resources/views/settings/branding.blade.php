@extends('components.app-shell')

@section('title', 'Tampilan & Branding')
@section('page-title', 'Tampilan & Branding')
@section('page-description', 'Logo, nama aplikasi, dan warna tema')

@section('page-actions')
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<form method="POST" action="{{ route('settings.branding.update') }}"
      enctype="multipart/form-data"
      x-data="brandingPreview({
          name: @js(old('app.name', $values['app.name']['value'] ?? config('branding.platform.full_name'))),
          short: @js(old('app.short_name', $values['app.short_name']['value'] ?? config('branding.platform.name'))),
          tagline: @js(old('app.tagline', $values['app.tagline']['value'] ?? '')),
          primary: @js(old('branding.primary_color', $values['branding.primary_color']['value'] ?? '#681D2A')),
          accent: @js(old('branding.accent_color', $values['branding.accent_color']['value'] ?? '#A83C4C')),
      })"
      class="grid lg:grid-cols-5 gap-4 sm:gap-5">

    @csrf
    @method('PUT')

    <div class="lg:col-span-3 space-y-4 sm:space-y-5">
        <x-card title="Identitas Aplikasi" icon="sparkles">
            <div class="space-y-4">
                <x-form-field name="app.name" label="Nama Aplikasi" required
                              :value="old('app.name', $values['app.name']['value'] ?? '')"
                              x-model="name" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-form-field name="app.short_name" label="Nama Pendek" required maxlength="20"
                                  :value="old('app.short_name', $values['app.short_name']['value'] ?? '')"
                                  x-model="short"
                                  hint="Dipakai pada PWA & layar sempit." />
                    <x-form-field name="app.tagline" label="Tagline"
                                  :value="old('app.tagline', $values['app.tagline']['value'] ?? '')" />
                </div>
            </div>
        </x-card>

        <x-card title="Logo" icon="image" description="Format JPG, PNG, WEBP, atau SVG. Maksimal 1 MB.">
            <div class="space-y-4">
                @foreach (['branding.logo' => 'Logo Utama', 'branding.icon' => 'Ikon / Favicon'] as $key => $label)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="shrink-0 w-24 h-24 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface-muted)] grid place-items-center overflow-hidden">
                            @php $src = app(\App\Services\SettingsService::class)->asset($key); @endphp
                            @if ($src)
                                <img src="{{ $src }}" alt="{{ $label }}" class="max-w-full max-h-full object-contain">
                            @else
                                <x-icon name="image" class="w-6 h-6 text-[var(--app-text-subtle)]" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <input type="file" name="{{ $key }}" accept=".jpg,.jpeg,.png,.webp,.svg"
                                   class="block w-full text-caption text-[var(--app-text-muted)] file:mr-3
                                          file:rounded-[var(--radius-md)] file:border-0
                                          file:bg-[var(--app-surface-muted)] file:px-3 file:py-2
                                          file:text-caption file:font-semibold file:cursor-pointer">
                            @if ($src)
                                <button type="button" class="mt-2 text-caption text-[var(--app-danger)] hover:underline"
                                        form="remove-{{ str_replace('.', '-', $key) }}">
                                    Hapus aset ini
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Warna Tema" icon="chart-bar"
                description="Hanya warna hex yang diterima. Kontras teks otomatis divalidasi.">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="branding.primary_color" class="label">Warna Utama</label>
                    <div class="flex gap-2">
                        <input type="color" id="branding.primary_color_picker" x-model="primary"
                               class="w-12 h-10 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-transparent cursor-pointer">
                        <input type="text" name="branding.primary_color" x-model="primary" required
                               pattern="#[0-9a-fA-F]{6}" maxlength="7"
                               class="field font-mono uppercase">
                    </div>
                    <p class="help-text">Tombol, tautan, dan sorotan.</p>
                </div>
                <div>
                    <label for="branding.accent_color" class="label">Warna Aksen</label>
                    <div class="flex gap-2">
                        <input type="color" id="branding.accent_color_picker" x-model="accent"
                               class="w-12 h-10 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-transparent cursor-pointer">
                        <input type="text" name="branding.accent_color" x-model="accent" required
                               pattern="#[0-9a-fA-F]{6}" maxlength="7"
                               class="font-mono uppercase field">
                    </div>
                    <p class="help-text">Detail sekunder.</p>
                </div>
            </div>

            <div x-show="! contrastOk" x-cloak
                 class="mt-4 rounded-[var(--radius-md)] border border-[var(--app-danger)]/30 bg-[var(--app-danger-soft)] px-3.5 py-3">
                <p class="text-small font-semibold text-[var(--app-danger)] flex items-center gap-2">
                    <x-icon name="alert-triangle" class="w-4 h-4" />
                    Kontras warna utama terlalu rendah
                </p>
                <p class="mt-1 text-caption text-[var(--app-danger)]">
                    Teks putih di atas warna ini akan sulit dibaca. Pilih warna yang lebih gelap.
                </p>
            </div>
        </x-card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <x-icon name="save" class="w-4 h-4" />
                Simpan Branding
            </button>
        </div>
    </div>

    {{-- ---------- Live preview ---------- --}}
    <div class="lg:col-span-2">
        <div class="lg:sticky lg:top-24 space-y-4">
            <x-card title="Pratinjau Langsung" icon="eye" description="Perubahan tampil di sini sebelum disimpan.">
                <div :style="themeVars" class="rounded-[var(--radius-lg)] overflow-hidden border border-[var(--app-border)]">
                    {{-- Mini sidebar --}}
                    <div class="flex" style="height:280px">
                        <div class="w-16 shrink-0 p-2 space-y-1.5" :style="`background:${darken(primary, 55)}`">
                            <template x-for="i in 4" :key="i">
                                <div class="h-7 rounded-md"
                                     :style="i === 1 ? `background:${primary}` : 'background:rgba(255,255,255,.08)'"></div>
                            </template>
                        </div>
                        <div class="flex-1 bg-white p-3 overflow-hidden">
                            <p class="text-[13px] font-bold truncate" :style="`color:${darken(primary, 25)}`" x-text="name || 'Nama Aplikasi'"></p>
                            <p class="text-[11px] text-slate-400 truncate" x-text="tagline"></p>

                            <div class="mt-3 space-y-1.5">
                                <div class="h-2 w-2/3 rounded bg-slate-200"></div>
                                <div class="h-2 w-1/2 rounded bg-slate-200"></div>
                            </div>

                            <div class="mt-3 flex gap-1.5">
                                <span class="px-2 py-1 rounded-md text-[10px] font-semibold text-white"
                                      :style="`background:${primary}`">Tombol Utama</span>
                                <span class="px-2 py-1 rounded-md text-[10px] font-semibold border border-slate-200 text-slate-500">Sekunder</span>
                            </div>

                            <div class="mt-3 flex gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold text-white"
                                      :style="`background:${primary}`">Terverifikasi</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold"
                                      :style="`background:${lighten(accent, 82)};color:${darken(accent, 20)}`">Menunggu</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-600">Perlu Perbaikan</span>
                            </div>

                            <div class="mt-3 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full" :style="`width:65%;background:${accent}`"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 space-y-1.5 text-caption">
                    <p class="text-[var(--app-text-muted)]">Warna utama: <span class="font-mono font-semibold text-[var(--app-text)]" x-text="primary"></span></p>
                    <p class="text-[var(--app-text-muted)]">Warna aksen: <span class="font-mono font-semibold text-[var(--app-text)]" x-text="accent"></span></p>
                    <p class="text-[var(--app-text-muted)]">
                        Kontras dengan teks putih:
                        <span class="font-mono font-semibold" :class="contrastOk ? 'text-[var(--app-success)]' : 'text-[var(--app-danger)]'"
                              x-text="ratio + ':1'"></span>
                    </p>
                </div>
            </x-card>
        </div>
    </div>
</form>

{{-- Remove-asset forms live outside the main form (nested forms are invalid). --}}
@foreach (['branding.logo', 'branding.icon'] as $key)
    <form id="remove-{{ str_replace('.', '-', $key) }}" method="POST"
          action="{{ route('settings.branding.remove', ['key' => $key]) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endforeach

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('brandingPreview', (config) => ({
        name: config.name, short: config.short, tagline: config.tagline,
        primary: config.primary, accent: config.accent,

        get themeVars() {
            return `--p:${this.primary};--p-h:${this.darken(this.primary, -12)};--p-s:${this.lighten(this.primary, 90)};`;
        },
        get ratio() { return this.contrast(this.primary, '#FFFFFF'); },
        get contrastOk() { return this.ratio >= 3; },

        hex(c) {
            let v = (c || '').replace('#', '');
            if (v.length === 3) v = v.split('').map((x) => x + x).join('');
            return [0, 2, 4].map((i) => parseInt(v.substr(i, 2), 16) || 0);
        },
        rgb(c) { const [r, g, b] = this.hex(c); return [r, g, b]; },
        shade(c, p) {
            const [r, g, b] = this.rgb(c);
            const f = (x) => {
                const v = p >= 0 ? x + (255 - x) * (p / 100) : x * (1 + p / 100);
                return Math.max(0, Math.min(255, Math.round(v)));
            };
            return `rgb(${f(r)}, ${f(g)}, ${f(b)})`;
        },
        darken(c, p) { return this.shade(c, -Math.abs(p)); },
        lighten(c, p) { return this.shade(c, Math.abs(p)); },
        lum(c) {
            const [r, g, b] = this.rgb(c).map((v) => {
                v /= 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        },
        contrast(a, b) {
            const l1 = this.lum(a), l2 = this.lum(b);
            return Math.round(((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)) * 100) / 100;
        },
    }));
});
</script>
@endpush
@endsection
