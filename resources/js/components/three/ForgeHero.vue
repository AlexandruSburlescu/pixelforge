<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Fills its container with the Three.js "pixel forge". Three is fetched only
 * after the page has loaded and gone idle, and skipped for reduced motion, data-saver or no
 * WebGL; a static pixel pattern (pure CSS) stands in, so the frame never looks empty.
 */
const canvas = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);
let dispose: (() => void) | null = null;
let timer: number | null = null;
let idle: number | null = null;
let onLoad: (() => void) | null = null;

function shouldAnimate(): boolean {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const saveData = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData === true;
    const webgl = (() => {
        try {
            return !!document.createElement('canvas').getContext('webgl2');
        } catch {
            return false;
        }
    })();

    return !reduced && !saveData && webgl;
}

onMounted(() => {
    if (!canvas.value || !shouldAnimate()) return;

    const start = async () => {
        const { mount } = await import('@/lib/forgeScene');
        if (!canvas.value) return;
        const accent = getComputedStyle(document.documentElement).getPropertyValue('--ember').trim() || '#f0a050';
        dispose = mount(canvas.value, toHex(accent));
        requestAnimationFrame(() => (ready.value = true));
    };

    // Only after the page has fully loaded and settled: three (~130 KB) must
    // never compete with the first paint or hydration, even on slow phones.
    const ric = (window as Window & { requestIdleCallback?: (cb: () => void, o?: { timeout: number }) => number }).requestIdleCallback;
    const schedule = () => {
        const wait = window.setTimeout(() => {
            idleHandle = ric ? ric(() => void start(), { timeout: 2000 }) : window.setTimeout(() => void start(), 0);
        }, 800);
        idleHandle = wait;
    };

    if (document.readyState === 'complete') {
        schedule();
    } else {
        window.addEventListener('load', schedule, { once: true });
    }
});

onBeforeUnmount(() => {
    if (idleHandle !== null) {
        (window.cancelIdleCallback ?? window.clearTimeout)(idleHandle);
    }
    dispose?.();
});

/** CSS colors (incl. oklch) → hex via a 1px canvas, so three gets a plain color. */
function toHex(color: string): string {
    const ctx = document.createElement('canvas').getContext('2d');
    if (!ctx) return '#f0a050';
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;

    return `#${[r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('')}`;
}
</script>

<template>
    <div class="absolute inset-0" aria-hidden="true">
        <div class="pixel-fallback absolute inset-0 transition-opacity duration-1000" :class="ready ? 'opacity-0' : 'opacity-100'" />
        <canvas
            ref="canvas"
            class="absolute inset-0 size-full transition-opacity duration-1000"
            :class="ready ? 'opacity-100' : 'opacity-0'"
        />
    </div>
</template>

<style scoped>
/* Static stand-in: a soft glowing pixel grid, same idea as the 3D scene. */
.pixel-fallback {
    background-image:
        radial-gradient(circle at 50% 42%, color-mix(in oklch, var(--ember) 55%, transparent) 0, transparent 38%),
        radial-gradient(circle, color-mix(in oklch, var(--foreground) 22%, transparent) 1px, transparent 1.5px);
    background-size: 100% 100%, 9px 9px;
    mask-image: radial-gradient(circle at 50% 50%, black 45%, transparent 72%);
}
</style>
