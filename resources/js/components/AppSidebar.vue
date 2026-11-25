<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ChartColumn, Images, Sparkles, Wallet } from '@lucide/vue';
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { studio } from '@/routes';
import { index } from '@/routes/generations';
import { index as financialReports } from '@/routes/admin/financials';
import { index as wallet } from '@/routes/wallet';
import type { NavItem } from '@/types';

const { isCurrentUrl } = useCurrentUrl();
const page = usePage();
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Studio',
        href: studio(),
        icon: Sparkles,
        isActive: isCurrentUrl(studio()),
    },
    { title: 'My Generations', href: index(), icon: Images },
    { title: 'Credit wallet', href: wallet(), icon: Wallet },
    ...(page.props.can.viewFinancialReports
        ? [
              {
                  title: 'Financial reports',
                  href: financialReports(),
                  icon: ChartColumn,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Pixelforge reference',
        href: 'https://pixelforge.promptedit.dev/docs',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="studio()">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
