const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

const makeRandom = (seed) => {
    let value = seed >>> 0;
    return () => {
        value = (value * 1664525 + 1013904223) >>> 0;
        return value / 4294967296;
    };
};

const createWalker = (random, width, height, index, count) => {
    const depth = 0.55 + random() * 0.65;
    const size = (18 + random() * 22) * depth;

    return {
        x: random() * width,
        y: height - (10 + random() * Math.min(80, height * 0.3)),
        size,
        depth,
        speed: (10 + random() * 18) * depth,
        phase: random() * Math.PI * 2,
        direction: random() > 0.5 ? 1 : -1,
        opacity: 0.12 + (index / Math.max(1, count)) * 0.12,
    };
};

const drawWalker = (ctx, walker, time, parallax) => {
    const stride = Math.sin(time * 0.008 * walker.depth + walker.phase);
    const bounce = Math.abs(Math.sin(time * 0.008 * walker.depth + walker.phase)) * 1.4;
    const x = walker.x + parallax * walker.depth;
    const y = walker.y - bounce;
    const s = walker.size;
    const color = `rgba(255,255,255,${walker.opacity})`;

    ctx.save();
    ctx.translate(x, y);
    ctx.scale(walker.direction, 1);
    ctx.strokeStyle = color;
    ctx.fillStyle = color;
    ctx.lineWidth = Math.max(1, s * 0.075);
    ctx.lineCap = 'round';

    // Head and torso: intentionally abstract so the animation stays a quiet
    // atmosphere layer, not a distracting cartoon over the school photograph.
    ctx.beginPath();
    ctx.arc(0, -s * 0.82, s * 0.16, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(0, -s * 0.62);
    ctx.lineTo(0, -s * 0.12);
    ctx.stroke();

    // Arms and legs move opposite each other like a gentle walking cycle.
    ctx.beginPath();
    ctx.moveTo(0, -s * 0.54);
    ctx.lineTo(-s * 0.27, -s * 0.28 + stride * s * 0.12);
    ctx.moveTo(0, -s * 0.54);
    ctx.lineTo(s * 0.27, -s * 0.28 - stride * s * 0.12);
    ctx.moveTo(0, -s * 0.12);
    ctx.lineTo(-s * 0.22, s * 0.34 - stride * s * 0.18);
    ctx.moveTo(0, -s * 0.12);
    ctx.lineTo(s * 0.22, s * 0.34 + stride * s * 0.18);
    ctx.stroke();

    // Small backpack mark, a subtle nod to a school community.
    ctx.globalAlpha = walker.opacity * 0.6;
    ctx.beginPath();
    ctx.arc(-s * 0.13, -s * 0.42, s * 0.11, Math.PI * 0.5, Math.PI * 1.5);
    ctx.stroke();
    ctx.restore();
};

const mountCrowdCanvas = (canvas) => {
    const ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return () => {};

    const random = makeRandom(Number(canvas.dataset.seed || 39));
    const walkers = [];
    const state = { width: 0, height: 0, dpr: 1, parallax: 0, targetParallax: 0, visible: true };
    let animationFrame = 0;
    let lastTime = performance.now();

    const resize = () => {
        const rect = canvas.getBoundingClientRect();
        state.width = Math.max(1, rect.width);
        state.height = Math.max(1, rect.height);
        state.dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = Math.round(state.width * state.dpr);
        canvas.height = Math.round(state.height * state.dpr);
        walkers.length = 0;

        const count = Math.round(clamp(state.width / 72, 8, 22));
        for (let index = 0; index < count; index += 1) {
            walkers.push(createWalker(random, state.width, state.height, index, count));
        }
    };

    const render = (time) => {
        if (!state.visible) {
            animationFrame = requestAnimationFrame(render);
            return;
        }

        const delta = Math.min(40, time - lastTime);
        lastTime = time;
        state.parallax += (state.targetParallax - state.parallax) * 0.06;
        ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
        ctx.clearRect(0, 0, state.width, state.height);

        walkers.forEach((walker) => {
            if (!reducedMotion()) {
                walker.x += walker.speed * walker.direction * (delta / 1000);
                if (walker.direction > 0 && walker.x - walker.size > state.width + 30) walker.x = -walker.size - 30;
                if (walker.direction < 0 && walker.x + walker.size < -30) walker.x = state.width + walker.size + 30;
            }
            drawWalker(ctx, walker, reducedMotion() ? 0 : time, state.parallax);
        });

        animationFrame = requestAnimationFrame(render);
    };

    const onPointerMove = (event) => {
        const rect = canvas.getBoundingClientRect();
        const ratio = (event.clientX - rect.left) / Math.max(1, rect.width);
        state.targetParallax = (ratio - 0.5) * 16;
    };

    const onPointerLeave = () => { state.targetParallax = 0; };
    const observer = new IntersectionObserver(([entry]) => { state.visible = entry.isIntersecting; }, { threshold: 0.05 });
    const resizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(resize) : null;

    resize();
    observer.observe(canvas);
    resizeObserver?.observe(canvas);
    canvas.addEventListener('pointermove', onPointerMove, { passive: true });
    canvas.addEventListener('pointerleave', onPointerLeave, { passive: true });
    animationFrame = requestAnimationFrame(render);

    return () => {
        cancelAnimationFrame(animationFrame);
        observer.disconnect();
        resizeObserver.disconnect();
        canvas.removeEventListener('pointermove', onPointerMove);
        canvas.removeEventListener('pointerleave', onPointerLeave);
    };
};

const cleanups = [...document.querySelectorAll('[data-landing-crowd]')].map(mountCrowdCanvas);

if (import.meta.hot) {
    import.meta.hot.dispose(() => cleanups.forEach((cleanup) => cleanup()));
}
