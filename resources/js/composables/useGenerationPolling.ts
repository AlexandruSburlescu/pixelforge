import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import type { Ref } from 'vue';
import { status as statusRoute } from '@/routes/generations';
import { IN_FLIGHT } from '@/types/generation';
import type { Generation } from '@/types/generation';

/**
 * Live status without websockets: while any shown generation is in flight, ask
 * the light /generations/status endpoint every 2s (only for those ids), merge
 * the answers in place, and stop as soon as nothing is moving or the tab is hidden.
 */
export function useGenerationPolling(items: Ref<Generation[]>, intervalMs = 2000) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let stopped = false;

    const inFlightIds = () =>
        items.value.filter((g) => IN_FLIGHT.includes(g.status)).map((g) => g.id);

    async function tick() {
        timer = null;
        const ids = inFlightIds();

        if (stopped || ids.length === 0 || document.hidden) {
            return;
        }

        try {
            const url = statusRoute({ query: { ids } }).url;
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const body = (await response.json()) as { generations: Generation[] };
                const byId = new Map(body.generations.map((g) => [g.id, g]));
                const finishedNow = items.value.some(
                    (g) => IN_FLIGHT.includes(g.status) && byId.get(g.id) && !IN_FLIGHT.includes(byId.get(g.id)!.status),
                );

                items.value = items.value.map((g) => byId.get(g.id) ?? g);

                if (finishedNow) {
                    router.reload({ only: ['auth'] }); // balance changed
                }
            }
        } catch {
            // Network blip: try again on the next tick.
        }

        schedule();
    }

    function schedule() {
        if (!stopped && timer === null && inFlightIds().length > 0) {
            timer = setTimeout(tick, intervalMs);
        }
    }

    const onVisibility = () => !document.hidden && schedule();

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibility);
        schedule();
    });

    watch(() => inFlightIds().join(','), schedule);

    onBeforeUnmount(() => {
        stopped = true;
        document.removeEventListener('visibilitychange', onVisibility);
        if (timer) clearTimeout(timer);
    });
}
