<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { PRODUCT_CONFIG } from '@/config/landing';
</script>

<template>
    <Head title="Términos de Uso" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100 font-sans">
        <header class="border-b border-zinc-800/80 bg-zinc-950/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-4xl items-center justify-between px-6">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400">
                        <AppLogoIcon class="h-5 w-5" />
                    </div>
                    <span class="text-lg font-extrabold tracking-tight text-white">
                        Clini<span class="text-emerald-400">Core</span>
                    </span>
                </Link>
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-400 transition-colors hover:text-white"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Volver al inicio
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-14 space-y-8">
            <div class="space-y-3 border-b border-zinc-800 pb-6">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 font-mono text-xs text-emerald-400">
                    <FileText class="h-3.5 w-3.5" />
                    Condiciones del Servicio
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight text-white">Términos de Uso</h1>
                <p class="text-sm text-zinc-400">
                    Última actualización: {{ new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long' }) }}
                </p>
            </div>

            <div class="space-y-6 text-sm leading-relaxed text-zinc-300">
                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">1. Objeto del servicio</h2>
                    <p>
                        {{ PRODUCT_CONFIG.name }} ofrece una herramienta web de apoyo a la planificación de guardias y turnos en servicios hospitalarios. El cuadrante generado debe ser revisado y validado siempre por el responsable de la unidad antes de su publicación oficial.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">2. Modalidad de acceso y planes futuros</h2>
                    <p>
                        Actualmente el acceso a la plataforma se ofrece de forma gratuita durante la fase de lanzamiento. En caso de introducir planes comerciales por suscripción en el futuro, se notificará con antelación suficiente a los servicios activos sin cargos automáticos ni compromisos de permanencia.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">3. Uso adecuado</h2>
                    <p>
                        Los usuarios se comprometen a utilizar {{ PRODUCT_CONFIG.name }} únicamente para la coordinación interna de su equipo asistencial, evitando introducir datos personales de pacientes en campos de texto libre u observaciones.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">4. Contacto</h2>
                    <p>
                        Para cualquier consulta legal, técnica o comercial puedes escribirnos a
                        <a :href="`mailto:${PRODUCT_CONFIG.email}`" class="font-semibold text-emerald-400 underline underline-offset-4 hover:text-emerald-300">{{ PRODUCT_CONFIG.email }}</a>.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
