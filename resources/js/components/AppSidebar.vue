<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Coins, FolderGit2, History, ImagePlus, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as creditsIndex } from '@/routes/credits';
import { create as generate, index as history } from '@/routes/generations';
import type { Auth, NavItem } from '@/types';

const page = usePage();
const auth = computed(() => page.props.auth as Auth);

const mainNavItems: NavItem[] = [
    { title: 'Generate', href: generate(), icon: ImagePlus },
    { title: 'History', href: history(), icon: History },
    { title: 'Credits', href: creditsIndex(), icon: Coins },
];

const footerNavItems = computed<NavItem[]>(() => [
    ...(auth.value.isAdmin ? [{ title: 'Admin', href: '/admin', icon: ShieldCheck }] : []),
    { title: 'Pixelforge API docs', href: 'https://pixelforge.promptedit.dev/docs', icon: BookOpen },
    { title: 'Source code', href: 'https://github.com/AlexandruSburlescu/pixelforge', icon: FolderGit2 },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="generate()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <Link
                :href="creditsIndex()"
                class="mx-2 flex items-center justify-between rounded-lg border px-3 py-2 text-xs group-data-[collapsible=icon]:hidden hover:border-ember"
            >
                <span class="text-muted-foreground">Balance</span>
                <span class="font-mono text-sm tabular-nums">{{ auth.balance }}</span>
            </Link>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
