<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ForgeHero from '@/components/three/ForgeHero.vue';
import { login, register } from '@/routes';
import { create as generate } from '@/routes/generations';

type Pricing = {
    tiers: { quality: string; min: string; max: string; from: number; to: number }[];
    markup: number;
    receipt: {
        lines: { label: string; tokens: string; rate: string; cost: string }[];
        cost: string;
        markup: string;
        charge: string;
    };
    welcome: string;
    welcomeImages: number;
};

withDefaults(defineProps<{ canRegister?: boolean; pricing?: Pricing | null }>(), {
    canRegister: true,
    pricing: null,
});

const steps = [
    { title: 'Write a prompt', body: 'Pick a size and quality, add up to four reference images if you like.' },
    { title: 'See the range', body: 'Before you submit you see the lowest, likely and highest price. Only the highest is set aside.' },
    { title: 'Pay what it used', body: 'When the image is ready you pay for the tokens it actually used. The rest comes straight back.' },
];

const tierNote: Record<string, string> = {
    low: 'Quick drafts',
    medium: 'Everyday images',
    high: 'Most detail',
};
</script>

<template>
    <Head title="Forge images, pay exactly what they cost" />

    <a href="#main" class="sr-only z-50 rounded bg-background px-3 py-2 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

    <div class="relative min-h-dvh overflow-x-clip bg-background text-foreground">
        <header class="relative z-20 mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
            <Link href="/" class="flex items-center gap-2.5 font-semibold tracking-tight" aria-label="Pixelforge Studio home">
                <AppLogoIcon class="size-6 text-ember" />
                <span>Pixelforge Studio</span>
            </Link>
            <nav class="flex items-center gap-1 text-sm" aria-label="Account">
                <a href="#how" class="hidden rounded-md px-3 py-1.5 text-muted-foreground hover:text-foreground sm:inline-block">How it works</a>
                <a v-if="pricing" href="#prices" class="hidden rounded-md px-3 py-1.5 text-muted-foreground hover:text-foreground sm:inline-block">Prices</a>
                <Link v-if="$page.props.auth.user" :href="generate()" class="ml-2 rounded-md border px-4 py-1.5 hover:border-ember">Open studio</Link>
                <template v-else>
                    <Link :href="login()" class="rounded-md px-3 py-1.5 hover:bg-muted">Log in</Link>
                    <Link v-if="canRegister" :href="register()" class="ml-1 rounded-md border px-4 py-1.5 hover:border-ember">Sign up</Link>
                </template>
            </nav>
        </header>

        <main id="main">
            <!-- Hero -->
            <section class="relative isolate -mt-[72px] overflow-hidden pt-[72px]" aria-labelledby="hero-title">
                <div class="hero-backdrop pointer-events-none absolute inset-0 -z-10" aria-hidden="true" />
                <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 pt-12 pb-12 sm:px-8 sm:pb-16 lg:min-h-[80dvh] lg:pb-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,27rem)] lg:gap-10 lg:pt-10">
                    <div>
                        <p class="mb-5 inline-flex items-center gap-2 rounded-full border bg-background/60 px-3 py-1 font-mono text-[11px] tracking-wider text-muted-foreground uppercase backdrop-blur">
                            <span class="size-1.5 rounded-full bg-ember" aria-hidden="true" />
                            Image generation, billed by the token
                        </p>
                        <h1 id="hero-title" class="text-[2.6rem] leading-[1.02] font-semibold tracking-[-0.03em] sm:text-6xl lg:text-[4.1rem]">
                            <span class="block">Forge images.</span>
                            <span class="block text-muted-foreground">Pay exactly</span>
                            <span class="block text-muted-foreground">what they cost.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-base leading-relaxed text-muted-foreground sm:text-lg">
                            See the price before you click. Get charged for what the image actually used, to a billionth of a dollar. If it fails, it's free.
                        </p>
                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <Link
                                :href="$page.props.auth.user ? generate() : register()"
                                class="group inline-flex h-11 items-center gap-2 rounded-lg bg-ember px-5 font-medium text-ember-foreground shadow-[0_0_0_1px_rgb(0_0_0/0.1),0_8px_30px_-8px_var(--ember)] transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ember focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                            >
                                {{ $page.props.auth.user ? 'Start generating' : `Start with ${pricing?.welcome ?? '$2.00'} free` }}
                                <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                            </Link>
                            <a href="#how" class="inline-flex h-11 items-center rounded-lg px-4 text-sm text-muted-foreground hover:text-foreground">See how billing works</a>
                        </div>
                        <ul class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-[13px] text-muted-foreground">
                            <li v-for="t in ['No card needed', 'Failed images are free', 'Live status']" :key="t" class="flex items-center gap-1.5">
                                <Check class="size-3.5 text-ember" aria-hidden="true" />{{ t }}
                            </li>
                        </ul>
                    </div>

                    <!-- A generation being forged: the product, not decoration. -->
                    <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                        <div class="pointer-events-none absolute -inset-10 -z-10 rounded-full bg-ember/20 blur-3xl" aria-hidden="true" />
                        <div class="rounded-2xl border bg-card/70 p-1.5 shadow-[0_40px_120px_-40px_rgb(0_0_0/0.9)] backdrop-blur-sm" role="img" aria-label="Preview of an image being generated, with its price range">
                            <div class="flex items-center justify-between px-3 pt-1.5 pb-2.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-ember/40 px-2 py-0.5 font-mono text-[10.5px] tracking-wide text-ember uppercase">
                                    <span class="size-1.5 animate-pulse rounded-full bg-ember" />
                                    Processing
                                </span>
                                <span class="font-mono text-[11px] text-muted-foreground">1024 × 1024 · high</span>
                            </div>
                            <div class="relative aspect-square overflow-hidden rounded-xl border bg-[oklch(0.1_0_0)]">
                                <ForgeHero />
                                <div class="frame-corners pointer-events-none absolute inset-3" />
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3 pt-3 pb-2 font-mono text-[11.5px]">
                                <span class="text-muted-foreground">
                                    Estimate
                                    <span class="text-foreground tabular-nums">{{ pricing ? `${pricing.tiers[2].min} – ${pricing.tiers[2].max}` : 'live' }}</span>
                                </span>
                                <span class="text-muted-foreground">Failed? <span class="text-foreground">$0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How it works + a real bill -->
            <section id="how" class="mx-auto max-w-6xl scroll-mt-8 px-5 py-14 sm:px-8 sm:py-20" aria-labelledby="how-title">
                <div class="grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)] lg:gap-16">
                    <div>
                        <h2 id="how-title" class="text-2xl font-semibold tracking-tight sm:text-3xl">How billing works</h2>
                        <p class="mt-3 max-w-md text-muted-foreground">
                            Pixelforge charges by the token, and part of the count is only known once the image is done. So you see a range first and pay the exact amount after.
                        </p>
                        <ol class="mt-10 space-y-8">
                            <li v-for="(s, i) in steps" :key="s.title" class="grid grid-cols-[2.5rem_1fr] gap-4">
                                <span class="font-mono text-sm text-ember tabular-nums" aria-hidden="true">0{{ i + 1 }}</span>
                                <div>
                                    <h3 class="font-medium">{{ s.title }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ s.body }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <figure v-if="pricing" class="self-start rounded-2xl border bg-card p-1 shadow-[0_30px_80px_-40px_rgb(0_0_0/0.8)]">
                        <div class="rounded-xl border border-dashed p-5 sm:p-6">
                            <div class="flex items-baseline justify-between">
                                <p class="font-mono text-[11px] tracking-wider text-muted-foreground uppercase">Receipt</p>
                                <p class="font-mono text-[11px] text-muted-foreground">high · 1536×1024 · 1 ref</p>
                            </div>
                            <table class="mt-5 w-full font-mono text-[12.5px]">
                                <caption class="sr-only">Cost of one finished generation, line by line</caption>
                                <thead class="sr-only">
                                    <tr><th scope="col">Item</th><th scope="col">Tokens and rate</th><th scope="col">Cost (USD)</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="l in pricing.receipt.lines" :key="l.label" class="align-baseline">
                                        <th scope="row" class="py-1.5 pr-3 text-left font-normal">{{ l.label }}</th>
                                        <td class="py-1.5 pr-3 text-right text-muted-foreground">
                                            <span class="hidden sm:inline">{{ l.tokens }} × {{ l.rate }}</span>
                                            <span class="sm:hidden">{{ l.tokens }}</span>
                                        </td>
                                        <td class="py-1.5 text-right tabular-nums">{{ l.cost }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t border-dashed">
                                    <tr>
                                        <th scope="row" colspan="2" class="pt-3 pb-1 text-left font-normal text-muted-foreground">Pixelforge's cost</th>
                                        <td class="pt-3 pb-1 text-right tabular-nums">{{ pricing.receipt.cost }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" colspan="2" class="py-1 text-left font-normal text-muted-foreground">Our {{ pricing.markup }}%</th>
                                        <td class="py-1 text-right tabular-nums">{{ pricing.receipt.markup }}</td>
                                    </tr>
                                    <tr class="text-sm">
                                        <th scope="row" colspan="2" class="pt-3 text-left font-semibold">You pay</th>
                                        <td class="pt-3 text-right font-semibold text-ember tabular-nums">${{ pricing.receipt.charge }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <figcaption class="px-4 py-3 text-xs text-muted-foreground">
                            The finished generation from Pixelforge's docs, priced at today's rates. Every image in your history shows its own.
                        </figcaption>
                    </figure>
                </div>
            </section>

            <!-- Prices -->
            <section v-if="pricing" id="prices" class="mx-auto max-w-6xl scroll-mt-8 px-5 pb-24 sm:px-8" aria-labelledby="prices-title">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 id="prices-title" class="text-2xl font-semibold tracking-tight sm:text-3xl">What a square image costs</h2>
                        <p class="mt-3 max-w-lg text-muted-foreground">
                            For a short prompt at 1024×1024. Wider images and reference images cost a bit more, and you always see the exact range before you submit.
                        </p>
                    </div>
                </div>
                <ul class="mt-10 grid gap-px overflow-hidden rounded-2xl border bg-border sm:grid-cols-3">
                    <li v-for="t in pricing.tiers" :key="t.quality" class="bg-background p-6">
                        <p class="text-sm font-medium capitalize">{{ t.quality }}</p>
                        <p class="text-xs text-muted-foreground">{{ tierNote[t.quality] }}</p>
                        <p class="mt-6 font-mono text-[1.4rem] tracking-tight tabular-nums">
                            {{ t.min }}<span class="mx-1.5 text-muted-foreground">–</span>{{ t.max }}
                        </p>
                        <div class="relative mt-4 h-1.5 rounded-full bg-muted" aria-hidden="true">
                            <div
                                class="absolute inset-y-0 rounded-full bg-gradient-to-r from-ember/50 to-ember"
                                :style="{ left: `${t.from}%`, width: `${Math.max(2, t.to - t.from)}%` }"
                            />
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">per image, {{ pricing.markup }}% included</p>
                    </li>
                </ul>
            </section>

            <!-- Closing CTA -->
            <section class="border-t" aria-labelledby="cta-title">
                <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-5 py-14 sm:flex-row sm:items-center sm:px-8">
                    <div>
                        <h2 id="cta-title" class="text-xl font-semibold tracking-tight">Try it with free credits</h2>
                        <p class="mt-1 text-sm text-muted-foreground">New accounts start with {{ pricing?.welcome ?? '$2.00' }}<template v-if="pricing?.welcomeImages">: at least {{ pricing.welcomeImages }} medium-quality images</template>.</p>
                    </div>
                    <Link
                        :href="$page.props.auth.user ? generate() : register()"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border px-5 font-medium hover:border-ember"
                    >
                        {{ $page.props.auth.user ? 'Open studio' : 'Create an account' }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="border-t" aria-label="About this site">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-8 text-xs text-muted-foreground sm:flex-row sm:justify-between sm:px-8">
                <p>Built for the PromptEdit take-home. Pixelforge is a simulated provider: images are placeholders and credits aren't real money.</p>
                <p class="flex gap-4">
                    <a href="https://pixelforge.promptedit.dev/docs" target="_blank" rel="noopener" class="hover:text-foreground">Pixelforge API</a>
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Faint graph paper + a warm glow behind the preview card. */
.hero-backdrop {
    background-image:
        radial-gradient(60rem 30rem at 75% 40%, color-mix(in oklch, var(--ember) 9%, transparent), transparent 70%),
        linear-gradient(color-mix(in oklch, var(--foreground) 4%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in oklch, var(--foreground) 4%, transparent) 1px, transparent 1px);
    background-size: auto, 40px 40px, 40px 40px;
    mask-image: linear-gradient(to bottom, black 55%, transparent);
}

/* Camera-viewfinder corners on the preview frame. */
.frame-corners {
    --c: color-mix(in oklch, var(--foreground) 35%, transparent);
    background:
        linear-gradient(var(--c), var(--c)) top left / 14px 1px,
        linear-gradient(var(--c), var(--c)) top left / 1px 14px,
        linear-gradient(var(--c), var(--c)) top right / 14px 1px,
        linear-gradient(var(--c), var(--c)) top right / 1px 14px,
        linear-gradient(var(--c), var(--c)) bottom left / 14px 1px,
        linear-gradient(var(--c), var(--c)) bottom left / 1px 14px,
        linear-gradient(var(--c), var(--c)) bottom right / 14px 1px,
        linear-gradient(var(--c), var(--c)) bottom right / 1px 14px;
    background-repeat: no-repeat;
}
</style>
