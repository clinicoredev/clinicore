<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Acceso por invitación',
        description: 'El alta de nuevos servicios en CliniCore se gestiona de forma asistida.',
    },
});
</script>

<template>
    <Head title="Acceso por invitación" />

    <div class="space-y-4 text-center text-sm text-muted-foreground">
        <p>
            El registro público está desactivado. Para dar de alta a tu servicio hospitalario, escribe a
            <a
                href="mailto:clinicore.dev@gmail.com?subject=Solicitud%20de%20alta%20en%20CliniCore"
                class="font-medium text-emerald-500 underline underline-offset-4 hover:text-emerald-400"
            >
                clinicore.dev@gmail.com
            </a>.
        </p>
        <div>
            <TextLink :href="login()" class="underline underline-offset-4">
                Volver a iniciar sesión
            </TextLink>
        </div>
    </div>
</template>
