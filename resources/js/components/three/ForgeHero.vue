<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Three.js never blocks the first paint: the scene module (and three itself) is
 * fetched only after the page is idle, and skipped entirely for reduced motion,
 * data-saver connections or browsers without WebGL. A CSS grid stands in.
 */
const canvas = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);
let dispose: (() => void) | null = null;
let idleHandle: number | null = null;

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

    const ric = (window as Window & { requestIdleCallback?: (cb: () => void, o?: { timeout: number }) => number }).requestIdleCallback;
    idleHandle = ric ? ric(() => void start(), { timeout: 1500 }) : window.setTimeout(() => void start(), 300);
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
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="hero-grid absolute inset-0" />
        <canvas
            ref="canvas"
            class="absolute inset-0 size-full transition-opacity duration-1000"
            :class="ready ? 'opacity-100' : 'opacity-0'"
        />
        <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-background to-transparent" />
    </div>
</template>

<style scoped>
.hero-grid {
    background-image:
        linear-gradient(color-mix(in oklch, var(--foreground) 5%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in oklch, var(--foreground) 5%, transparent) 1px, transparent 1px);
    background-size: 32px 32px;
    mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}
</style>
