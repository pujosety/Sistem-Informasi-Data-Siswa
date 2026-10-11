@php
    $assistantSchool = data_get($school ?? [], 'name') ?: data_get($brand ?? [], 'name') ?: 'sekolah ini';
@endphp

<div
    x-data="lyflaAiAssistant({ schoolName: @js($assistantSchool) })"
    x-init="init()"
    class="lyfla-ai"
    @keydown.escape.window="close()"
>

    <section
        id="lyfla-ai-panel"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-[.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-[.98]"
        class="lyfla-ai-panel"
        role="dialog"
        aria-labelledby="lyfla-ai-title"
        aria-modal="false"
    >
        <header class="lyfla-ai-header">
            <div class="flex min-w-0 items-center gap-3">
                <template x-if="open">
                    <img :src="headerMascotSrc" alt="" class="lyfla-ai-header-mascot">
                </template>
                <div class="min-w-0">
                    <p id="lyfla-ai-title" class="truncate text-small font-bold text-[var(--app-text)]">Lala</p>
                    <p class="truncate text-caption text-[var(--app-text-muted)]">Asisten Informasi Sekolah · {{ $assistantSchool }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" class="lyfla-ai-icon-button" @click="reset()" aria-label="Mulai chat baru" title="Chat baru">↻</button>
                <button type="button" class="lyfla-ai-icon-button" @click="close()" aria-label="Tutup Lala" title="Tutup">×</button>
            </div>
        </header>

        <div class="lyfla-ai-messages" x-ref="messages" aria-live="polite" :aria-busy="loading.toString()">
            <template x-for="(item, index) in messages" :key="index">
                <article class="lyfla-ai-message" :class="item.role === 'user' ? 'is-user' : 'is-assistant'">
                    <div class="lyfla-ai-message-bubble" x-text="item.content"></div>
                    <template x-if="item.sources?.length">
                        <details class="lyfla-ai-sources">
                            <summary>Sources (<span x-text="item.sources.length"></span>)</summary>
                            <template x-for="source in item.sources" :key="source.title + source.type">
                                <a class="lyfla-ai-source" :href="source.url || null" :target="source.url ? '_blank' : null" :rel="source.url ? 'noreferrer' : null">
                                    <span x-text="source.title"></span>
                                    <small x-text="source.type"></small>
                                </a>
                            </template>
                        </details>
                    </template>
                </article>
            </template>
            <div x-show="loading" role="status" aria-live="polite" class="lyfla-ai-message is-assistant">
                <div class="lyfla-ai-message-bubble lyfla-ai-thinking"><span></span><span></span><span></span><em>Sedang mencari informasi...</em></div>
            </div>
        </div>

        <div x-show="messages.length === 1 && !loading" class="lyfla-ai-suggestions" role="group" aria-label="Pertanyaan cepat">
            <button type="button" @click="sendSuggestion('Apa saja ekstrakurikuler di sekolah ini?')">Ekstrakurikuler</button>
            <button type="button" @click="sendSuggestion('Kapan PPDB dibuka?')">PPDB</button>
            <button type="button" @click="sendSuggestion('Apa program unggulan sekolah?')">Program</button>
            <button type="button" @click="sendSuggestion('Bagaimana cara menghubungi sekolah?')">Kontak</button>
        </div>

        <form class="lyfla-ai-input-row" @submit.prevent="send()">
            <textarea x-model="draft" x-ref="input" @keydown.enter="handleEnter($event)" rows="1" maxlength="1200" placeholder="Tanyakan tentang sekolah..." aria-label="Pertanyaan untuk LYFLA AI"></textarea>
            <button type="submit" class="lyfla-ai-send" :disabled="loading || !draft.trim()" aria-label="Kirim pertanyaan">➤</button>
        </form>
    </section>

    <button
        x-show="!open"
        x-ref="trigger"
        type="button"
        class="lyfla-ai-mascot"
        @click="toggle()"
        :class="{ 'is-open': open, 'is-thinking': loading, 'is-switching': switching }"
        :aria-label="open ? 'Tutup Lala School Assistant' : 'Buka Lala School Assistant'"
        aria-controls="lyfla-ai-panel"
        :aria-expanded="open.toString()"
    >
        <span class="lyfla-ai-ground" aria-hidden="true"></span>
        <img :src="mascotSrc" alt="Lala" draggable="false">
    </button>
</div>
