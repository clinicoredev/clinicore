<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarClock,
    CalendarDays,
    GraduationCap,
    Layers,
    LayoutGrid,
    PartyPopper,
    Users,
} from '@lucide/vue';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel Principal',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Calendario Completo',
        href: '/calendario-completo',
        icon: Layers,
    },
    {
        title: 'Guardias Adjuntos',
        href: '/guardias',
        icon: CalendarDays,
    },
    {
        title: 'Guardias Residentes',
        href: '/residentes/guardias',
        icon: GraduationCap,
    },
    {
        title: 'Intercambios de Guardia',
        href: '/permutas',
        icon: ArrowLeftRight,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Permisos y Ausencias',
        href: '/ausencias',
        icon: CalendarClock,
    },
    {
        title: 'Festivos Oficiales',
        href: '/festivos',
        icon: PartyPopper,
    },
    {
        title: 'Directorio Facultativo',
        href: '/personal',
        icon: Users,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
