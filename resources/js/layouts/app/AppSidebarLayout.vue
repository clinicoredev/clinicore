<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const clinicoreBrand = computed(() => {
    const data = (page.props as Record<string, any>).clinicore ?? {};
    return {
        name: data.name ?? 'CliniCore',
        domain: data.domain ?? 'clinicore.es',
        includeWatermark: data.includeWatermark ?? true,
    };
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
            <div
                v-if="clinicoreBrand.includeWatermark"
                class="print-watermark-footer hidden"
            >
                Cuadrante generado con {{ clinicoreBrand.name }} · {{ clinicoreBrand.domain }}
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
