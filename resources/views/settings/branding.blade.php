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

@php
    $settingValues = collect($values)->mapWithKeys(fn (array $item, string $key) => [$key => $item['value']])->all();
    $assetKeys = ['branding.logo' => 'Logo Utama', 'branding.logo_dark' => 'Logo Dark Mode', 'branding.logo_compact' => 'Logo Compact', 'branding.favicon' => 'Favicon', 'branding.app_icon' => 'App Icon', 'branding.login_logo' => 'Logo Login'];
    $assetUrls = collect($assetKeys)->mapWithKeys(fn (string $label, string $key) => [$key => app(\App\Services\SettingsService::class)->asset($key)])->all();
@endphp

<form method="POST" action="{{ route('settings.branding.update') }}"
      enctype="multipart/form-data"
      x-data="brandingCustomizer({
          name: @js(old('app.name', $values['app.name']['value'] ?? config('branding.platform.full_name'))),
          short: @js(old('app.short_name', $values['app.short_name']['value'] ?? config('branding.platform.name'))),
          tagline: @js(old('app.tagline', $values['app.tagline']['value'] ?? '')),
          primary: @js(old('branding.primary_color', $values['branding.primary_color']['value'] ?? '#681D2A')),
          accent: @js(old('branding.accent_color', $values['branding.accent_color']['value'] ?? '#A83C4C')),
          values: @js($settingValues),
          initial: @js($settingValues),
          assets: @js($assetUrls),
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

        <x-card title="Detail Identitas" icon="file-text">
            <div class="space-y-4">
                <x-form-field name="app.description" type="textarea" rows="3" label="Deskripsi Singkat" :value="old('app.description', $values['app.description']['value'] ?? '')" x-model="values['app.description']" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="app.portal_label" label="Label Portal" :value="old('app.portal_label', $values['app.portal_label']['value'] ?? 'Portal Akademik')" x-model="values['app.portal_label']" />
                    <x-form-field name="app.copyright" label="Teks Copyright" :value="old('app.copyright', $values['app.copyright']['value'] ?? '© 2026 SMP 1 LYFLA')" x-model="values['app.copyright']" />
                </div>
            </div>
        </x-card>

        <x-card title="Logo dan Brand Assets" icon="image" description="Tarik file ke area upload atau pilih file. Asset kosong memakai fallback LYFLA.">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($assetKeys as $key => $label)
                    <div class="asset-dropzone" x-data="{ dragging: false }" :class="dragging && 'is-dragging'"
                         @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                         @drop.prevent="dragging = false; pickFile($event, '{{ $key }}')" @click="$refs.file.click()">
                        <input x-ref="file" class="sr-only" type="file" name="{{ $key }}" accept=".jpg,.jpeg,.png,.webp,.svg" @change="pickFile($event, '{{ $key }}')">
                        <template x-if="previews['{{ $key }}'] || assets['{{ $key }}']"><img :src="previews['{{ $key }}'] || assets['{{ $key }}']" alt="{{ $label }}" class="asset-preview"></template>
                        <template x-if="! previews['{{ $key }}'] && ! assets['{{ $key }}']"><span class="grid size-10 place-items-center rounded-[var(--radius-md)] bg-[var(--app-primary-soft)] text-[var(--app-primary)]"><x-icon name="image" class="size-5" /></span></template>
                        <div class="min-w-0 flex-1"><strong>{{ $label }}</strong><span>Tarik file ke sini atau pilih file</span><small>JPG, PNG, WEBP, SVG. Maksimal 1 MB.</small></div>
                        <button type="button" class="asset-browse" @click.stop="$refs.file.click()">Pilih</button>
                    </div>
                @endforeach
            </div>
        </x-card>

        @if (false)
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
        @endif

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

        <x-card title="Palet Warna Lengkap" icon="palette" description="Atur token global atau buat variasi otomatis dari primary.">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['branding.primary_hover'=>'Primary Hover','branding.background'=>'Background','branding.surface'=>'Surface','branding.sidebar'=>'Sidebar','branding.sidebar_active'=>'Sidebar Active','branding.text_primary'=>'Text Primary','branding.text_secondary'=>'Text Secondary','branding.border'=>'Border','branding.success'=>'Success','branding.warning'=>'Warning','branding.error'=>'Error'] as $key => $label)
                    <label class="color-control"><span>{{ $label }}</span><div><input type="color" x-model="values['{{ $key }}']"><input class="field font-mono uppercase" name="{{ $key }}" maxlength="7" pattern="#[0-9a-fA-F]{6}" x-model="values['{{ $key }}']"></div></label>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><button type="button" class="btn btn-secondary" @click="generatePalette()">Buat Palet Otomatis</button><button type="button" class="btn btn-secondary" @click="resetGroup(colorKeys)">Kembalikan Warna</button></div>
            <div class="preset-grid mt-4"><template x-for="preset in presets" :key="preset.name"><button type="button" class="preset-card" @click="applyPreset(preset)"><span class="preset-swatch" :style="`--preset-primary:${preset.primary};--preset-bg:${preset.background};--preset-sidebar:${preset.sidebar}`"></span><strong x-text="preset.name"></strong><small x-text="preset.description"></small></button></template></div>
            <div class="contrast-result mt-4" :class="contrastOk ? 'is-good' : 'is-danger'"><span class="font-bold" x-text="contrastOk ? 'AA - Baik' : 'Gagal - Perlu Perbaikan'"></span><span x-text="`Kontras dengan teks putih: ${ratio}:1`"></span><button type="button" class="text-small font-semibold underline" x-show="! contrastOk" @click="fixContrast()">Perbaiki Otomatis</button></div>
        </x-card>

        <section class="surface p-4 sm:p-5" x-data="{ open: false }">
            <button type="button" class="branding-section-title" @click="open = ! open" :aria-expanded="open"><span><span class="section-kicker">Hierarchy</span><strong>Tipografi</strong><small>Font utama, bobot heading, dan skala.</small></span><x-icon name="chevron-down" class="size-5 transition-transform" ::class="open && 'rotate-180'" /></button>
            <div x-show="open" x-collapse class="mt-5 grid gap-4 sm:grid-cols-3"><label class="field-group"><span>Font Utama</span><select class="field" name="theme.font_family" x-model="values['theme.font_family']"><option>System Default</option><option>Manrope</option><option>Inter</option><option>Plus Jakarta Sans</option><option>Poppins</option><option>DM Sans</option></select></label><label class="field-group"><span>Font Weight Heading</span><select class="field" name="theme.heading_weight" x-model="values['theme.heading_weight']"><option value="500">Medium</option><option value="600">Semibold</option><option value="700">Bold</option></select></label><label class="field-group"><span>Skala Font</span><select class="field" name="theme.font_scale" x-model="values['theme.font_scale']"><option value="compact">Compact</option><option value="default">Default</option><option value="large">Large</option></select></label></div>
        </section>

        <section class="surface p-4 sm:p-5" x-data="{ open: false }">
            <button type="button" class="branding-section-title" @click="open = ! open" :aria-expanded="open"><span><span class="section-kicker">UI system</span><strong>Komponen & Kepadatan</strong><small>Radius, bayangan, tombol, tabel, card, dan badge.</small></span><x-icon name="chevron-down" class="size-5 transition-transform" ::class="open && 'rotate-180'" /></button>
            <div x-show="open" x-collapse class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><label class="field-group"><span>Radius Sudut</span><select class="field" name="theme.radius" x-model="values['theme.radius']"><option value="sharp">Tegas</option><option value="soft">Sedikit Rounded</option><option value="rounded">Rounded</option><option value="very-rounded">Sangat Rounded</option></select></label><label class="field-group"><span>Bayangan</span><select class="field" name="theme.shadow" x-model="values['theme.shadow']"><option value="none">Tanpa Bayangan</option><option value="thin">Tipis</option><option value="soft">Lembut</option><option value="elevated">Elevated</option></select></label><label class="field-group"><span>Kepadatan Tampilan</span><select class="field" name="theme.density" x-model="values['theme.density']"><option value="compact">Compact</option><option value="comfortable">Nyaman</option><option value="spacious">Lapang</option></select></label><label class="field-group"><span>Gaya Tombol</span><select class="field" name="component.button_style" x-model="values['component.button_style']"><option value="solid">Solid</option><option value="soft">Soft</option><option value="outline">Outline</option><option value="minimal">Minimal</option></select></label><label class="field-group"><span>Gaya Tabel</span><select class="field" name="component.table_style" x-model="values['component.table_style']"><option value="clean">Clean</option><option value="bordered">Bordered</option><option value="striped">Striped</option><option value="compact">Compact</option></select></label><label class="field-group"><span>Gaya Card</span><select class="field" name="component.card_style" x-model="values['component.card_style']"><option value="flat">Flat</option><option value="bordered">Bordered</option><option value="soft-shadow">Soft Shadow</option><option value="elevated">Elevated</option></select></label><label class="field-group"><span>Gaya Badge</span><select class="field" name="component.badge_style" x-model="values['component.badge_style']"><option value="solid">Solid</option><option value="soft">Soft</option><option value="outline">Outline</option></select></label><label class="field-group"><span>Radius Badge</span><select class="field" name="component.badge_radius" x-model="values['component.badge_radius']"><option value="pill">Pill</option><option value="rounded">Rounded</option></select></label></div>
        </section>

        <section class="surface p-4 sm:p-5" x-data="{ open: false }">
            <button type="button" class="branding-section-title" @click="open = ! open" :aria-expanded="open"><span><span class="section-kicker">Workspace</span><strong>Sidebar & Header</strong><small>Menu tetap terbaca di desktop, tablet, dan mobile.</small></span><x-icon name="chevron-down" class="size-5 transition-transform" ::class="open && 'rotate-180'" /></button>
            <div x-show="open" x-collapse class="mt-5 grid gap-4 sm:grid-cols-2"><label class="field-group"><span>Active Style</span><select class="field" name="sidebar.active_style" x-model="values['sidebar.active_style']"><option value="filled">Filled</option><option value="pill">Pill</option><option value="left-border">Left Border</option><option value="soft">Soft Highlight</option></select></label><label class="field-group"><span>Posisi Logo</span><select class="field" name="sidebar.logo_position" x-model="values['sidebar.logo_position']"><option value="left">Kiri</option><option value="center">Center</option></select></label><label class="field-group"><span>Lebar Sidebar</span><select class="field" name="sidebar.width" x-model="values['sidebar.width']"><option value="compact">Compact</option><option value="default">Default</option><option value="wide">Wide</option></select></label><label class="field-group"><span>Background Header</span><select class="field" name="header.background" x-model="values['header.background']"><option value="white">Putih</option><option value="surface">Surface</option><option value="primary">Primary</option></select></label><label class="toggle-field"><input type="hidden" name="header.border" value="0"><input type="checkbox" name="header.border" value="1" x-model="values['header.border']"> Border Header</label><label class="toggle-field"><input type="hidden" name="header.shadow" value="0"><input type="checkbox" name="header.shadow" value="1" x-model="values['header.shadow']"> Shadow Header</label><label class="toggle-field"><input type="hidden" name="header.search" value="0"><input type="checkbox" name="header.search" value="1" x-model="values['header.search']"> Search Bar</label><label class="toggle-field"><input type="hidden" name="header.breadcrumb" value="0"><input type="checkbox" name="header.breadcrumb" value="1" x-model="values['header.breadcrumb']"> Breadcrumb</label><label class="toggle-field"><input type="hidden" name="header.sticky" value="0"><input type="checkbox" name="header.sticky" value="1" x-model="values['header.sticky']"> Header Sticky</label></div>
        </section>

        <section class="surface p-4 sm:p-5" x-data="{ open: false }">
            <button type="button" class="branding-section-title" @click="open = ! open" :aria-expanded="open"><span><span class="section-kicker">Authentication</span><strong>Halaman Login</strong><small>Atur komposisi visual tanpa mengubah form Laravel.</small></span><x-icon name="chevron-down" class="size-5 transition-transform" ::class="open && 'rotate-180'" /></button>
            <div x-show="open" x-collapse class="mt-5 grid gap-4 sm:grid-cols-2"><label class="field-group"><span>Layout</span><select class="field" name="login.layout" x-model="values['login.layout']"><option value="centered">Centered</option><option value="split">Split</option><option value="brand-panel">Brand Panel</option></select></label><label class="field-group"><span>Background</span><select class="field" name="login.background" x-model="values['login.background']"><option value="solid">Solid</option><option value="gradient">Gradient</option><option value="image">Image</option></select></label></div>
        </section>

        <section class="surface p-4 sm:p-5" x-data="{ open: false }">
            <button type="button" class="branding-section-title" @click="open = ! open" :aria-expanded="open"><span><span class="section-kicker">Optional</span><strong>Lanjutan</strong><small>Mode tampilan, dekorasi, ikon, dan CSS Super Admin.</small></span><x-icon name="chevron-down" class="size-5 transition-transform" ::class="open && 'rotate-180'" /></button>
            <div x-show="open" x-collapse class="mt-5 space-y-4"><div class="grid gap-4 sm:grid-cols-3"><label class="field-group"><span>Mode Tampilan</span><select class="field" name="theme.mode" x-model="values['theme.mode']"><option value="light">Light Only</option><option value="dark">Dark Only</option><option value="system">Ikuti Sistem</option><option value="user">Pengguna Bisa Memilih</option></select></label><label class="field-group"><span>Background Style</span><select class="field" name="theme.background_style" x-model="values['theme.background_style']"><option value="white">Pure White</option><option value="warm">Warm Gray</option><option value="soft-tint">Soft Tint</option><option value="custom">Custom</option></select></label><label class="field-group"><span>Intensitas Background</span><input class="field" type="range" min="0" max="100" name="theme.background_intensity" x-model="values['theme.background_intensity']"><output class="text-caption" x-text="values['theme.background_intensity'] + '%'" /></label><label class="field-group"><span>Elemen Dekoratif</span><select class="field" name="theme.decorative" x-model="values['theme.decorative']"><option value="none">None</option><option value="subtle-gradient">Subtle Gradient</option><option value="soft-grid">Soft Grid</option><option value="light-noise">Very Light Noise</option></select></label><label class="field-group"><span>Gaya Ikon</span><select class="field" name="theme.icon_style" x-model="values['theme.icon_style']"><option value="outline">Outline</option><option value="rounded">Rounded</option><option value="filled">Filled</option><option value="duotone">Duotone</option></select></label></div>@if (auth()->user()?->hasRole('super_admin'))<label class="field-group"><span>Custom CSS <small>(Super Admin)</small></span><textarea class="field font-mono text-caption" name="advanced.custom_css" rows="6" maxlength="20000" x-model="values['advanced.custom_css']"></textarea><small>Gunakan hanya untuk penyesuaian kecil. CSS diterapkan global setelah disimpan.</small></label>@endif</div>
        </section>

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
                <div class="preview-toolbar mb-3">
                    <div class="flex flex-wrap gap-1">
                        <button type="button" class="preview-tab" :class="previewMode === 'dashboard' && 'is-active'" @click="previewMode = 'dashboard'">Dashboard</button>
                        <button type="button" class="preview-tab" :class="previewMode === 'form' && 'is-active'" @click="previewMode = 'form'">Form</button>
                        <button type="button" class="preview-tab" :class="previewMode === 'table' && 'is-active'" @click="previewMode = 'table'">Tabel</button>
                        <button type="button" class="preview-tab" :class="previewMode === 'login' && 'is-active'" @click="previewMode = 'login'">Login</button>
                        <button type="button" class="preview-tab" :class="previewMode === 'mobile' && 'is-active'" @click="previewMode = 'mobile'">Mobile</button>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" class="device-button" :class="previewDevice === 'desktop' && 'is-active'" @click="previewDevice = 'desktop'">Desktop</button>
                        <button type="button" class="device-button" :class="previewDevice === 'tablet' && 'is-active'" @click="previewDevice = 'tablet'">Tablet</button>
                        <button type="button" class="device-button" :class="previewDevice === 'mobile' && 'is-active'" @click="previewDevice = 'mobile'">Mobile</button>
                    </div>
                </div>
                <div :style="themeVars" class="preview-frame device-desktop">
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
    Alpine.data('brandingCustomizer', (config) => ({
        name: config.name,
        short: config.short,
        tagline: config.tagline,
        primary: config.primary,
        accent: config.accent,
        values: { ...(config.values || {}) },
        initial: { ...(config.initial || {}) },
        assets: { ...(config.assets || {}) },
        previews: {},
        previewMode: 'dashboard',
        previewDevice: 'desktop',
        colorKeys: ['branding.primary_color', 'branding.primary_hover', 'branding.accent_color', 'branding.background', 'branding.surface', 'branding.sidebar', 'branding.sidebar_active', 'branding.text_primary', 'branding.text_secondary', 'branding.border', 'branding.success', 'branding.warning', 'branding.error'],
        get dirty() { return JSON.stringify(this.values) !== JSON.stringify(this.initial); },
        get themeVars() { return `--p:${this.values['branding.primary_color'] || this.primary};--p-h:${this.darken(this.values['branding.primary_color'] || this.primary, -12)};--p-s:${this.lighten(this.values['branding.primary_color'] || this.primary, 90)};`; },
        get ratio() { return this.contrast(this.values['branding.primary_color'] || this.primary, '#FFFFFF'); },
        get contrastOk() { return this.ratio >= 3; },
        pickFile(event, key) {
            const file = event.dataTransfer?.files?.[0] || event.target?.files?.[0];
            if (!file) return;
            this.previews[key] = URL.createObjectURL(file);
        },
        generatePalette() {
            const primary = this.values['branding.primary_color'] || this.primary;
            this.values['branding.primary_hover'] = this.shade(primary, -12);
            this.values['branding.sidebar_active'] = this.shade(primary, -4);
            this.values['branding.background'] = this.values['branding.background'] || this.shade(primary, 96);
            this.values['branding.surface'] = this.values['branding.surface'] || '#FFFFFF';
            this.values['branding.border'] = this.values['branding.border'] || this.shade(primary, 82);
        },
        applyPreset(preset) {
            Object.assign(this.values, {
                'branding.primary_color': preset.primary,
                'branding.accent_color': preset.accent,
                'branding.background': preset.background,
                'branding.sidebar': preset.sidebar,
                'branding.surface': '#FFFFFF',
                'branding.text_primary': '#241A1C',
                'branding.text_secondary': '#5C4A4E',
                'branding.border': '#E7D8D9',
            });
            this.generatePalette();
        },
        fixContrast() { this.values['branding.primary_color'] = this.shade(this.values['branding.primary_color'] || this.primary, -25); this.generatePalette(); },
        resetGroup(keys) { (Array.isArray(keys) ? keys : [keys]).forEach((key) => { this.values[key] = this.initial[key] ?? ''; }); },
        resetAll() { this.values = { ...this.initial }; this.previews = {}; },
        hex(c) { let v = (c || '').replace('#', ''); if (v.length === 3) v = v.split('').map((x) => x + x).join(''); return [0, 2, 4].map((i) => parseInt(v.substr(i, 2), 16) || 0); },
        rgb(c) { return this.hex(c); },
        shade(c, p) { const f = (x) => p >= 0 ? x + (255 - x) * (p / 100) : x * (1 + p / 100); return `rgb(${this.rgb(c).map((x) => Math.max(0, Math.min(255, Math.round(f(x))))).join(', ')})`; },
        darken(c, p) { return this.shade(c, -Math.abs(p)); },
        lighten(c, p) { return this.shade(c, Math.abs(p)); },
        lum(c) { return this.rgb(c).map((v) => v / 255).map((v) => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)).reduce((a, v, i) => a + v * [0.2126, 0.7152, 0.0722][i], 0); },
        contrast(a, b) { const l1 = this.lum(a), l2 = this.lum(b); return Math.round(((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)) * 100) / 100; },
    }));
});
</script>
@endpush
@endsection
