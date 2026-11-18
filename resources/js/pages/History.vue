<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GenerationCard from '@/components/generation/GenerationCard.vue';
import { Button } from '@/components/ui/button';
import { useGenerationPolling } from '@/composables/useGenerationPolling';
import { create as generateRoute } from '@/routes/generations';
import { IN_FLIGHT } from '@/types/generation';
import type { CursorPage, Generation } from '@/types/generation';

const props = defineProps<{ generations: CursorPage<Generation> }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'History', href: '/history' }] },
});

const items = ref<Generation[]>([...props.generations.data]);
const nextUrl = ref(props.generations.next_page_url);
const loadingMore = ref(false);

watch(
    () => props.generations,
    (page) => {
        // First page replaces (e.g. after a reload); later pages append.
        items.value = page.prev_page_url ? [...items.value, ...page.data] : [...page.data];
        nextUrl.value = page.next_page_url;
    },
);

useGenerationPolling(items);

const filter = ref<'all' | 'running' | 'succeeded' | 'failed'>('all');
const shown = computed(() =>
    items.value.filter((g) =>
        filter.value === 'all' ? true
        : filter.value === 'running' ? IN_FLIGHT.includes(g.status)
        : g.status === filter.value,
    ),
);
const running = computed(() => items.value.filter((g) => IN_FLIGHT.includes(g.status)).length);

function loadMore() {
    if (!nextUrl.value) return;
    loadingMore.value = true;
    router.get(nextUrl.value, {}, {
        only: ['generations'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => (loadingMore.value = false),
    });
}
</script>

<template>
    <Head title="History" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">History</h1>
                <p class="text-sm text-muted-foreground">
                    Every generation with its estimate and what you were actually charged.
                    <template v-if="running">
                        <span class="text-ember">{{ running }} running</span>, updating live.
                    </template>
                </p>
            </div>

            <div class="inline-flex rounded-lg border p-1 text-sm" role="tablist" aria-label="Filter generations">
                <button
                    v-for="f in (['all', 'running', 'succeeded', 'failed'] as const)"
                    :key="f"
                    role="tab"
                    :aria-selected="filter === f"
                    class="rounded-md px-3 py-1 capitalize transition-colors aria-selected:bg-foreground aria-selected:text-background"
                    @click="filter = f"
                >{{ f }}</button>
            </div>
        </header>

        <div v-if="items.length === 0" class="grid place-items-center gap-4 rounded-xl border border-dashed p-12 text-center">
            <p class="text-sm text-muted-foreground">No generations yet.</p>
            <Button as-child class="bg-ember text-ember-foreground hover:bg-ember/90">
                <Link :href="generateRoute()"><ImagePlus /> Generate your first image</Link>
            </Button>
        </div>

        <template v-else>
            <p v-if="shown.length === 0" class="text-sm text-muted-foreground">Nothing in this filter on the loaded pages.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <GenerationCard v-for="g in shown" :key="g.id" :generation="g" />
            </div>
            <div v-if="nextUrl" class="flex justify-center">
                <Button variant="outline" :disabled="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Loading…' : 'Load more' }}
                </Button>
            </div>
        </template>
    </div>
</template>
