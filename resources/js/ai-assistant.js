const mascotBase = '/assets/lyfla-ai/mascot/';
const mascotMap = {
    'top-left': 'source-1.png',
    top: 'source-2.png',
    'top-right': 'source-3.png',
    left: 'source-5.png',
    center: 'source-6.png',
    right: 'source-7.png',
    'bottom-left': 'source-4.png',
    bottom: 'source-8.png',
    'bottom-right': 'source-7.png',
};

function directionFor(dx, dy, deadZone = 34) {
    if (Math.hypot(dx, dy) < deadZone) return 'center';
    const angle = Math.atan2(dy, dx) * (180 / Math.PI);
    if (angle >= -22.5 && angle < 22.5) return 'right';
    if (angle >= 22.5 && angle < 67.5) return 'bottom-right';
    if (angle >= 67.5 && angle < 112.5) return 'bottom';
    if (angle >= 112.5 && angle < 157.5) return 'bottom-left';
    if (angle >= 157.5 || angle < -157.5) return 'left';
    if (angle >= -157.5 && angle < -112.5) return 'top-left';
    if (angle >= -112.5 && angle < -67.5) return 'top';
    return 'top-right';
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('lyflaAiAssistant', ({ schoolName }) => ({
    schoolName,
    open: false,
    loading: false,
    draft: '',
    conversationId: null,
    direction: 'center',

    messages: [{
        role: 'assistant',
        content: `Halo! 👋\nSaya asisten virtual ${schoolName}.\nAda informasi sekolah yang ingin kamu cari?`,
        sources: [],
    }],
    get mascotSrc() {
        return mascotBase + (mascotMap[this.direction] || mascotMap.center);
    },
    init() {
    },
    toggle() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => this.$refs.input?.focus());
    },
    close() { this.open = false; },
    reset() {
        this.conversationId = null;
        this.messages = [{ role: 'assistant', content: `Halo! 👋\nSaya asisten virtual ${this.schoolName}.\nAda informasi sekolah yang ingin kamu cari?`, sources: [] }];
        this.draft = '';
    },
    setDirection(direction) { this.direction = direction; },
    trackPointer(event) {
        if (window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const rect = event.currentTarget.getBoundingClientRect();
        this.setDirection(directionFor(event.clientX - (rect.left + rect.width / 2), event.clientY - (rect.top + rect.height / 2)));
    },
    handleEnter(event) {
        if (!event.shiftKey) {
            event.preventDefault();
            this.send();
        }
    },
    sendSuggestion(message) { this.draft = message; this.send(); },
    async send() {
        const message = this.draft.trim();
        if (!message || this.loading) return;
        this.messages.push({ role: 'user', content: message, sources: [] });
        this.draft = '';
        this.loading = true;
        this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight; });
        try {
            const response = await fetch('/api/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ message, conversation_id: this.conversationId }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'AI unavailable');
            this.conversationId = payload.conversation_id || this.conversationId;
            this.messages.push({ role: 'assistant', content: payload.answer || 'Informasi tersebut belum tersedia di sistem sekolah.', sources: payload.sources || [] });
        } catch (error) {
            this.messages.push({ role: 'assistant', content: 'LYFLA AI sedang tidak dapat mengambil informasi. Coba lagi sebentar.', sources: [] });
        } finally {
            this.loading = false;
            this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight; });
        }
    },
    }));
});
