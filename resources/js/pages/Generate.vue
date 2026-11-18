<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ImagePlus, Loader2, Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GenerationCard from '@/components/generation/GenerationCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useGenerationPolling } from '@/composables/useGenerationPolling';
import { formatNano } from '@/lib/money';
import { estimate } from '@/lib/pricing';
import type { PricingTable } from '@/lib/pricing';
import { index as creditsIndex } from '@/routes/credits';
import { index as historyIndex, store } from '@/routes/generations';
import type { Generation } from '@/types/generation';

const props = defineProps<{
    pricing: PricingTable | null;
    markupBps: number;
    recent: Generation[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Generate', href: '/generate' }] },
});

const page = usePage();
const MAX_PROMPT = 4000;
const MAX_REFS = 4;

const newKey = (): string =>
    globalThis.crypto?.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
    idempotency_key: newKey(),
});

const sizes = [
    { value: '1024x1024', label: 'Square', w: 1, h: 1 },
    { value: '1536x1024', label: 'Landscape', w: 3, h: 2 },
    { value: '1024x1536', label: 'Portrait', w: 2, h: 3 },
];
const qualities = ['low', 'medium', 'high'];

const promptLength = computed(() => [...form.prompt.trim()].length);
const filledRefs = computed(() => form.reference_images.filter((u) => u.trim() !== '').length);

const quote = computed(() => {
    if (!props.pricing) return null;

    try {
        return estimate(props.pricing, form.prompt, form.size, form.quality, filledRefs.value, props.markupBps);
    } catch {
        return null;
    }
});

// 'credits' is a server-side error key that isn't a form field.
const creditsError = computed(() => (form.errors as Record<string, string | undefined>).credits);
const balanceText = computed(() => (page.props.auth as { balance: string | null }).balance);

// Same rule as the server: you need the maximum possible charge available.
// The server re-checks on submit; this just tells you before you try.
const shortfall = computed<bigint | null>(() => {
    const raw = (page.props.auth as { balanceNano: string | null }).balanceNano;
    if (!quote.value || raw === null) return null;
    const missing = quote.value.charge.max - BigInt(raw);

    return missing > 0n ? missing : null;
});

function addReference() {
    if (form.reference_images.length < MAX_REFS) form.reference_images.push('');
}

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('prompt', 'reference_images');
            form.idempotency_key = newKey(); // next submit is a new generation
        },
    });
}

// Recent generations, kept live by polling.
const recent = ref<Generation[]>([...props.recent]);
watch(() => props.recent, (value) => (recent.value = [...value]));
useGenerationPolling(recent);

const tryKeywords = ['[succeed]', '[fail]', '[slow]', '[duplicate]', '[overloaded]', '[blocked]'];
</script>

<template>
    <Head title="Generate" />

    <div class="mx-auto grid w-full max-w-7xl gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form class="space-y-6" @submit.prevent="submit">
            <header class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Generate an image</h1>
                <p class="text-sm text-muted-foreground">
                    You pay what Pixelforge charges plus 25%. The price is known exactly once the image is done; until then the most it can cost is held from your balance.
                </p>
            </header>

            <div class="space-y-2">
                <div class="flex items-baseline justify-between">
                    <label for="prompt" class="text-sm font-medium">Prompt</label>
                    <span class="font-mono text-[11px] tabular-nums" :class="promptLength > MAX_PROMPT ? 'text-destructive' : 'text-muted-foreground'">
                        {{ promptLength.toLocaleString() }} / {{ MAX_PROMPT.toLocaleString() }}
                    </span>
                </div>
                <textarea
                    id="prompt"
                    v-model="form.prompt"
                    v-focus
                    rows="5"
                    required
                    :maxlength="MAX_PROMPT + 200"
                    placeholder="A lighthouse on a cliff at dawn, oil painting"
                    class="w-full resize-y rounded-lg border bg-transparent px-3 py-2.5 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    @keydown.meta.enter="submit"
                    @keydown.ctrl.enter="submit"
                />
                <InputError :message="form.errors.prompt" />
                <p class="text-xs text-muted-foreground">
                    Test keywords:
                    <button
                        v-for="k in tryKeywords"
                        :key="k"
                        type="button"
                        class="mr-1 rounded border px-1 font-mono text-[11px] hover:border-ember hover:text-ember"
                        @click="form.prompt = (form.prompt.trim() + ' ' + k).trim()"
                    >{{ k }}</button>
                </p>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">Size</legend>
                <div class="grid grid-cols-3 gap-2">
                    <label
                        v-for="s in sizes"
                        :key="s.value"
                        class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 text-xs transition-colors has-checked:border-ember has-checked:bg-ember/5 has-focus-visible:ring-2 has-focus-visible:ring-ring"
                    >
                        <input v-model="form.size" type="radio" name="size" :value="s.value" class="sr-only" />
                        <span class="h-8 rounded-[3px] border-2 border-current opacity-70" :style="{ aspectRatio: `${s.w} / ${s.h}` }" aria-hidden="true" />
                        <span class="font-medium">{{ s.label }}</span>
                        <span class="font-mono text-[10px] text-muted-foreground">{{ s.value }}</span>
                    </label>
                </div>
                <InputError :message="form.errors.size" />
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">Quality</legend>
                <div class="inline-flex rounded-lg border p-1">
                    <label
                        v-for="q in qualities"
                        :key="q"
                        class="cursor-pointer rounded-md px-4 py-1.5 text-sm capitalize transition-colors has-checked:bg-foreground has-checked:text-background has-focus-visible:ring-2 has-focus-visible:ring-ring"
                    >
                        <input v-model="form.quality" type="radio" name="quality" :value="q" class="sr-only" />
                        {{ q }}
                    </label>
                </div>
                <InputError :message="form.errors.quality" />
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">
                    Reference images <span class="font-normal text-muted-foreground">(optional, up to 4 URLs, 765 tokens each)</span>
                </legend>
                <div v-for="(_, i) in form.reference_images" :key="i" class="flex gap-2">
                    <input
                        v-model="form.reference_images[i]"
                        type="url"
                        inputmode="url"
                        placeholder="https://example.com/style.png"
                        :aria-label="`Reference image ${i + 1}`"
                        class="h-9 min-w-0 flex-1 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Remove reference image ${i + 1}`" @click="form.reference_images.splice(i, 1)">
                        <X />
                    </Button>
                    <InputError :message="form.errors[`reference_images.${i}` as keyof typeof form.errors]" />
                </div>
                <Button v-if="form.reference_images.length < MAX_REFS" type="button" variant="outline" size="sm" @click="addReference">
                    <Plus /> Add reference URL
                </Button>
                <InputError :message="form.errors.reference_images" />
            </fieldset>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-4 lg:self-start">
            <section class="rounded-xl border bg-card p-4" aria-labelledby="cost-heading">
                <h2 id="cost-heading" class="text-sm font-medium">What it will cost</h2>

                <template v-if="quote">
                    <p class="mt-3 font-mono text-3xl tabular-nums" aria-live="polite">
                        ~{{ formatNano(quote.charge.expected) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Between <span class="font-mono text-foreground">{{ formatNano(quote.charge.min) }}</span>
                        and <span class="font-mono text-foreground">{{ formatNano(quote.charge.max) }}</span>.
                        Prompt length and the model's thinking time are only counted when it finishes.
                    </p>
                    <dl class="mt-3 grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 border-t pt-3 font-mono text-[11px] text-muted-foreground">
                        <dt>Held now (max)</dt><dd class="text-right text-foreground tabular-nums">{{ formatNano(quote.charge.max) }}</dd>
                        <dt>Your balance</dt><dd class="text-right tabular-nums">{{ balanceText }}</dd>
                        <dt>If it fails</dt><dd class="text-right tabular-nums">$0.00</dd>
                    </dl>
                </template>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    Live prices are unavailable right now. You'll see the exact estimate after submitting.
                </p>

                <div v-if="creditsError" class="mt-3 rounded-md bg-destructive/10 p-2 text-xs text-destructive" role="alert">
                    {{ creditsError }}
                    <Link :href="creditsIndex()" class="font-medium underline">Buy credits</Link>
                </div>

                <div v-if="shortfall && !creditsError" class="mt-3 rounded-md border border-ember/40 bg-ember/10 p-2 text-xs" role="status">
                    Not enough credits for the highest possible price: {{ formatNano(shortfall) }} short.
                    Pick a lower quality or
                    <Link :href="creditsIndex()" class="font-medium text-ember underline">buy credits</Link>.
                </div>

                <Button class="mt-4 w-full bg-ember text-ember-foreground hover:bg-ember/90" :disabled="form.processing || promptLength === 0 || shortfall !== null" @click="submit">
                    <Loader2 v-if="form.processing" class="animate-spin" />
                    <ImagePlus v-else />
                    Generate
                </Button>
                <p class="mt-2 text-center text-[11px] text-muted-foreground">Ctrl/⌘ + Enter</p>
            </section>

            <section v-if="recent.length" aria-labelledby="recent-heading" class="space-y-3">
                <div class="flex items-baseline justify-between">
                    <h2 id="recent-heading" class="text-sm font-medium">Recent</h2>
                    <Link :href="historyIndex()" class="text-xs text-muted-foreground hover:text-foreground">All history →</Link>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <GenerationCard v-for="g in recent" :key="g.id" :generation="g" compact />
                </div>
            </section>
        </aside>
    </div>
</template>
