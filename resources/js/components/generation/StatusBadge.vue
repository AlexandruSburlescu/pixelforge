<script setup lang="ts">
import { computed } from 'vue';
import type { Generation } from '@/types/generation';

const props = defineProps<{ generation: Generation }>();

const view = computed(() => {
    const g = props.generation;

    switch (g.status) {
        case 'pending':
            return g.retrying
                ? { label: 'Retrying', hint: 'Pixelforge was busy. Retrying shortly.', tone: 'wait' }
                : { label: 'Waiting', hint: 'Waiting for a free Pixelforge slot (3 run at once).', tone: 'wait' };
        case 'queued':
            return { label: 'Queued', hint: 'Accepted by Pixelforge, in its queue.', tone: 'wait' };
        case 'processing':
            return { label: 'Processing', hint: 'Pixelforge is generating it now.', tone: 'busy' };
        case 'succeeded':
            return { label: 'Succeeded', hint: 'Done. Charged the actual cost.', tone: 'ok' };
        default:
            return { label: 'Failed', hint: 'Failed. Nothing was charged.', tone: 'bad' };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 font-mono text-[11px] tracking-wide uppercase"
        :class="{
            'border-border text-muted-foreground': view.tone === 'wait',
            'border-ember/50 text-ember': view.tone === 'busy',
            'border-emerald-500/40 text-emerald-500 dark:text-emerald-400': view.tone === 'ok',
            'border-destructive/50 text-destructive': view.tone === 'bad',
        }"
        :title="view.hint"
        role="status"
    >
        <span
            class="size-1.5 rounded-full bg-current"
            :class="{ 'animate-pulse': view.tone === 'wait' || view.tone === 'busy' }"
            aria-hidden="true"
        />
        {{ view.label }}
    </span>
</template>
