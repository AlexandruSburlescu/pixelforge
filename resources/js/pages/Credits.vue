<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { purchase } from '@/routes/credits';
import type { CursorPage } from '@/types/generation';

type LedgerRow = {
    id: number;
    type: 'purchase' | 'hold' | 'settle' | 'refund';
    amount: string;
    amount_exact: string;
    balance_after: string;
    generation: { id: string; prompt: string } | null;
    note: string | null;
    created_at: string;
};

const props = defineProps<{ packs: number[]; ledger: CursorPage<LedgerRow> }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Credits', href: '/credits' }] },
});

const page = usePage();
const balance = computed(() => (page.props.auth as { balance: string | null }).balance);

const form = useForm({ amount: 0, idempotency_key: '' });

function buy(amount: number) {
    form.amount = amount;
    form.idempotency_key = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`.replace(/\./g, '');
    form.post(purchase().url, { preserveScroll: true });
}

const labels: Record<LedgerRow['type'], string> = {
    purchase: 'Credits added',
    hold: 'Held for generation',
    settle: 'Released after charge',
    refund: 'Refunded (failed)',
};

const when = (iso: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="Credits" />

    <div class="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-6">
        <section class="space-y-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Credits</h1>
                <p class="text-sm text-muted-foreground">1 credit = 1 US dollar. This is a demo: buying adds credits instantly and no payment is taken.</p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Balance</p>
                <p class="font-mono text-4xl tabular-nums">{{ balance }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="p in props.packs"
                    :key="p"
                    type="button"
                    :disabled="form.processing"
                    class="rounded-xl border p-4 text-left transition-colors hover:border-ember disabled:opacity-50"
                    @click="buy(p)"
                >
                    <span class="block font-mono text-2xl">${{ p }}</span>
                    <span class="text-xs text-muted-foreground">Buy credits</span>
                </button>
            </div>
            <p v-if="form.errors.amount" class="text-sm text-destructive">{{ form.errors.amount }}</p>
        </section>

        <section class="space-y-3" aria-labelledby="ledger-heading">
            <h2 id="ledger-heading" class="text-sm font-medium">Activity</h2>
            <p class="text-xs text-muted-foreground">
                Each generation first holds the most it could cost. When it finishes, the difference to the real price comes back; a failed one is refunded in full.
            </p>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">What</th>
                            <th class="px-3 py-2 text-right font-medium">Amount</th>
                            <th class="px-3 py-2 text-right font-medium">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.ledger.data" :key="row.id" class="border-t">
                            <td class="px-3 py-2 whitespace-nowrap text-muted-foreground">{{ when(row.created_at) }}</td>
                            <td class="px-3 py-2">
                                {{ labels[row.type] }}
                                <span v-if="row.generation" class="block truncate text-xs text-muted-foreground">{{ row.generation.prompt }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums" :class="row.amount.startsWith('-') ? '' : 'text-emerald-500'" :title="`$${row.amount_exact}`">
                                {{ row.amount.startsWith('-') ? row.amount : '+' + row.amount }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ row.balance_after }}</td>
                        </tr>
                        <tr v-if="props.ledger.data.length === 0">
                            <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">No activity yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
