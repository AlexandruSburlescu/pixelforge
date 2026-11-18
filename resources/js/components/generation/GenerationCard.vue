<script setup lang="ts">
import { computed, ref } from 'vue';
import StatusBadge from '@/components/generation/StatusBadge.vue';
import type { Generation } from '@/types/generation';

const props = defineProps<{ generation: Generation; compact?: boolean }>();

const imageFailed = ref(false);
const g = computed(() => props.generation);

const aspect = computed(() => {
    const [w, h] = g.value.size.split('x').map(Number);

    return `${w} / ${h}`;
});

const busy = computed(() => ['pending', 'queued', 'processing'].includes(g.value.status));
</script>

<template>
    <article class="group overflow-hidden rounded-xl border bg-card">
        <div
            class="relative w-full overflow-hidden bg-muted"
            :style="{ aspectRatio: aspect }"
        >
            <img
                v-if="g.image && !imageFailed"
                :src="g.image"
                :alt="g.prompt"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
                @error="imageFailed = true"
            />
            <div
                v-else-if="busy"
                class="forge-shimmer absolute inset-0"
                aria-hidden="true"
            />
            <div
                v-else
                class="absolute inset-0 grid place-items-center p-4 text-center text-xs text-muted-foreground"
            >
                <span v-if="g.status === 'failed'">No image: the generation failed.</span>
                <span v-else>Image link expired (Pixelforge keeps them 24h).</span>
            </div>

            <div class="absolute top-2 left-2">
                <StatusBadge :generation="g" class="bg-background/80 backdrop-blur" />
            </div>
        </div>

        <div class="space-y-3 p-3">
            <p class="line-clamp-2 text-sm" :title="g.prompt">{{ g.prompt }}</p>

            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 font-mono text-[11px]">
                <dt class="text-muted-foreground">Estimate</dt>
                <dd class="text-right tabular-nums">
                    {{ g.estimate.min }}–{{ g.estimate.max }}
                </dd>
                <dt class="text-muted-foreground">Charged</dt>
                <dd class="text-right tabular-nums" :title="g.charge?.exact ? `$${g.charge.exact} exactly` : undefined">
                    <template v-if="g.charge">{{ g.charge.display }}</template>
                    <template v-else-if="busy">held until done</template>
                    <template v-else>—</template>
                </dd>
                <template v-if="!compact">
                    <dt class="text-muted-foreground">Settings</dt>
                    <dd class="text-right">{{ g.size }} · {{ g.quality }}<template v-if="g.reference_images.length"> · {{ g.reference_images.length }} ref</template></dd>
                </template>
            </dl>

            <p v-if="g.error" class="rounded-md bg-destructive/10 px-2 py-1.5 text-xs text-destructive">
                {{ g.error }}
            </p>

            <details v-if="g.tokens && !compact" class="text-[11px] text-muted-foreground">
                <summary class="cursor-pointer select-none">Token usage</summary>
                <dl class="mt-1 grid grid-cols-2 gap-x-3 font-mono">
                    <dt>Text in</dt><dd class="text-right tabular-nums">{{ g.tokens.input_text.toLocaleString() }}</dd>
                    <dt>Images in</dt><dd class="text-right tabular-nums">{{ g.tokens.input_image.toLocaleString() }}</dd>
                    <dt>Output</dt><dd class="text-right tabular-nums">{{ g.tokens.output.toLocaleString() }}</dd>
                    <dt>Reasoning</dt><dd class="text-right tabular-nums">{{ g.tokens.reasoning.toLocaleString() }}</dd>
                </dl>
            </details>
        </div>
    </article>
</template>

<style scoped>
.forge-shimmer {
    background:
        linear-gradient(110deg, transparent 30%, color-mix(in oklch, var(--ember) 18%, transparent) 50%, transparent 70%),
        repeating-linear-gradient(0deg, color-mix(in oklch, var(--foreground) 4%, transparent) 0 1px, transparent 1px 8px),
        repeating-linear-gradient(90deg, color-mix(in oklch, var(--foreground) 4%, transparent) 0 1px, transparent 1px 8px);
    background-size: 220% 100%, auto, auto;
    animation: forge-sweep 1.6s linear infinite;
}

@keyframes forge-sweep {
    from { background-position: 120% 0, 0 0, 0 0; }
    to { background-position: -120% 0, 0 0, 0 0; }
}
</style>
