<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ForgeHero from '@/components/three/ForgeHero.vue';
import { login, register } from '@/routes';
import { create as generate } from '@/routes/generations';

withDefaults(defineProps<{ canRegister?: boolean }>(), { canRegister: true });

const points = [
    {
        title: 'Priced before you click',
        body: 'You see the lowest, likely and highest price as you type. The highest is held; the rest comes back.',
    },
    {
        title: 'Charged to the nano-dollar',
        body: 'Pixelforge reports tokens, we do the maths in whole nano-dollars: their cost plus 25%, never a float.',
    },
    {
        title: 'Failures are free',
        body: 'Rejected, overloaded or failed generations cost nothing, and a webhook that arrives twice is counted once.',
    },
];
</script>

<template>
    <Head title="Forge images, pay exactly what they cost" />

    <div class="relative min-h-dvh bg-background text-foreground">
        <header class="relative z-10 mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <span class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-6 text-ember" />
                Pixelforge Studio
            </span>
            <nav class="flex items-center gap-2 text-sm">
                <Link v-if="$page.props.auth.user" :href="generate()" class="rounded-md border px-4 py-1.5 hover:border-ember">
                    Open the studio
                </Link>
                <template v-else>
                    <Link :href="login()" class="rounded-md px-4 py-1.5 hover:bg-muted">Log in</Link>
                    <Link v-if="canRegister" :href="register()" class="rounded-md border px-4 py-1.5 hover:border-ember">Sign up</Link>
                </template>
            </nav>
        </header>

        <main>
            <section class="relative isolate overflow-hidden">
                <ForgeHero />
                <div class="relative mx-auto flex min-h-[70dvh] max-w-6xl flex-col justify-center px-6 py-24">
                    <p class="mb-4 font-mono text-xs tracking-widest text-ember uppercase">Image generation · usage-based</p>
                    <h1 class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">
                        Forge images. Pay exactly what they cost.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg text-muted-foreground">
                        Write a prompt, see the price range, get the image. Every generation is billed from the tokens it actually used.
                    </p>
                    <div class="mt-10 flex flex-wrap items-center gap-3">
                        <Link
                            :href="$page.props.auth.user ? generate() : register()"
                            class="inline-flex items-center gap-2 rounded-md bg-ember px-5 py-2.5 font-medium text-ember-foreground hover:bg-ember/90"
                        >
                            {{ $page.props.auth.user ? 'Start generating' : 'Get $2 of credits free' }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <a href="https://pixelforge.promptedit.dev/docs" target="_blank" rel="noopener" class="rounded-md px-4 py-2.5 text-sm text-muted-foreground hover:text-foreground">
                            How Pixelforge prices →
                        </a>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-6xl gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-3">
                <div v-for="p in points" :key="p.title" class="bg-background p-6">
                    <h2 class="font-medium">{{ p.title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ p.body }}</p>
                </div>
            </section>
        </main>

        <footer class="mx-auto max-w-6xl px-6 py-10 text-xs text-muted-foreground">
            A take-home build for PromptEdit. Pixelforge is a simulated provider: images are placeholders and credits are not real money.
        </footer>
    </div>
</template>
