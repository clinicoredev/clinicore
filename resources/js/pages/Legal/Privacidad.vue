<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { PRODUCT_CONFIG } from '@/config/landing';
</script>

<template>
    <Head title="Política de Privacidad" />

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
                    <ShieldCheck class="h-3.5 w-3.5" />
                    Privacidad y Protección de Datos
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight text-white">Política de Privacidad</h1>
                <p class="text-sm text-zinc-400">
                    Última actualización: {{ new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long' }) }}
                </p>
            </div>

            <div class="space-y-6 text-sm leading-relaxed text-zinc-300">
                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">1. Responsable y finalidad</h2>
                    <p>
                        {{ PRODUCT_CONFIG.name }} ({{ PRODUCT_CONFIG.domain }}) es una plataforma SaaS destinada exclusivamente a la organización interna de cuadrantes de guardias, salientes, ausencias y permutas en servicios hospitalarios.
                    </p>
                    <p>
                        <strong class="text-white">Importante:</strong> {{ PRODUCT_CONFIG.name }} no almacena ni procesa datos clínicos de pacientes ni historias clínicas. Únicamente trata los datos identificativos básicos de los profesionales del servicio (nombre, correo corporativo, rol y disponibilidad de turnos).
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">2. Aislamiento por servicio y seguridad</h2>
                    <p>
                        Cada servicio hospitalario opera en un entorno lógico aislado (arquitectura multi-tenant por centro y unidad). El acceso está protegido mediante autenticación segura, contraseñas cifradas y enlaces de invitación firmados con caducidad temporal.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">3. Integraciones opcionales (Google Calendar)</h2>
                    <p>
                        Cuando un profesional vincula voluntariamente su cuenta de Google Calendar mediante OAuth2, los tokens de acceso se almacenan cifrados únicamente para sincronizar sus turnos de guardia y pueden revocarse en cualquier momento desde el panel de usuario.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-white">4. Derechos y contacto</h2>
                    <p>
                        Puedes ejercer tus derechos de acceso, rectificación, supresión, limitación u oposición, o solicitar la baja completa de los datos de tu servicio escribiendo a
                        <a :href="`mailto:${PRODUCT_CONFIG.email}`" class="font-semibold text-emerald-400 underline underline-offset-4 hover:text-emerald-300">{{ PRODUCT_CONFIG.email }}</a>.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
