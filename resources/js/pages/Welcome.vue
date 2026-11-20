<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ArrowRight,
    Building2,
    CalendarCheck2,
    CalendarClock,
    Check,
    CheckCircle2,
    ChevronDown,
    Cpu,
    FileSpreadsheet,
    Layers,
    Mail,
    Menu,
    Play,
    Scale,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LandingCuadrantePreview from '@/components/landing/LandingCuadrantePreview.vue';
import { FAQ_ITEMS, PRICING_PLANS, PRODUCT_CONFIG } from '@/config/landing';

const props = withDefaults(
    defineProps<{
        canLogin?: boolean;
        canRegister?: boolean;
    }>(),
    {
        canLogin: true,
        canRegister: true,
    },
);

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props as Record<string, any>).auth?.user));

const mobileMenuOpen = ref(false);
const billingCycle = ref<'monthly' | 'annual'>('annual');
const openFaqIndex = ref<number | null>(0);

const accessRequestMailto = computed(
    () =>
        `mailto:${PRODUCT_CONFIG.email}?subject=Solicitud%20de%20alta%20en%20CliniCore&body=Hola%2C%0A%0AQueremos%20probar%20CliniCore%20en%20nuestro%20servicio%3A%0A-%20Hospital%3A%20%0A-%20Servicio%20/%20Unidad%3A%20%0A-%20Responsable%20(Jefe%20o%20Residente%20Mayor)%3A%20%0A-%20N%C2%BA%20aproximado%20de%20profesionales%3A%20`,
);

const toggleFaq = (idx: number) => {
    openFaqIndex.value = openFaqIndex.value === idx ? null : idx;
};

const navLinks = [
    { label: 'Funcionalidades', href: '#funcionalidades' },
    { label: 'Cómo funciona', href: '#como-funciona' },
    { label: 'Planes', href: '#planes' },
    { label: 'FAQ', href: '#faq' },
];

const painPoints = [
    'Hojas de Excel que se rompen en cuanto alguien pide un cambio o coge una baja.',
    'Horas revisando a mano salientes obligatorios, distancia mínima y libranzas.',
    'Discusiones en el servicio por quién lleva más festivos, fines de semana o días especiales.',
    'Cadenas interminables en WhatsApp para intercambiar guardias o cubrir vacantes.',
    'Recontar manualmente guardias de 17h y 24h a final de mes para pasarlos a Nóminas.',
];

const solutionPoints = [
    'Cuadrante generado en segundos respetando salientes, distancia mínima, bajas y rotaciones.',
    'Simulación previa (Dry-Run) y validación automática de conflictos antes de publicar.',
    'Equidad anual histórica (YTD) visible y transparente para todo el equipo.',
    'Permutas entre compañeros y bolsa de turnos vacantes validadas con un clic.',
    'Exportación inmediata a PDF para tablón/WhatsApp y Excel listo para RRHH/Nómina.',
];

const features = [
    {
        icon: Cpu,
        accent: 'emerald',
        title: 'Motor automático con reglas hospitalarias reales',
        description:
            'Genera el mes respetando salientes post-guardia, distancia mínima configurable, agrupaciones de fin de semana (V-S, S-D, V-S-D), limitaciones personales y prorrateo por jornada parcial o bajas.',
    },
    {
        icon: Scale,
        accent: 'teal',
        title: 'Equidad anual histórica (17h, 24h y especiales)',
        description:
            'Pondera el histórico acumulado del año diferenciando guardias laborables de 17h, fines de semana de 24h, festivos de 24h, días marcados como (ESPECIAL) y modalidad presencial o localizada.',
    },
    {
        icon: Layers,
        accent: 'purple',
        title: 'Adjuntos y Residentes + Calendario Global',
        description:
            'Gestiona en paralelo el cuadrante de Adjuntos y el de Residentes (con roles de Jefatura, Tutoría y Residente Mayor) y visualiza la cobertura completa en un calendario unificado.',
    },
    {
        icon: ArrowLeftRight,
        accent: 'amber',
        title: 'Permutas, bolsa de vacantes y ausencias',
        description:
            'Los miembros del equipo solicitan vacaciones, permisos o intercambios de guardia desde la app. CliniCore valida conflictos de saliente al instante antes de la aprobación final.',
    },
    {
        icon: FileSpreadsheet,
        accent: 'sky',
        title: 'Exportación a PDF y Excel para Nóminas',
        description:
            'Descarga en un clic un PDF apaisado con tabla de equidad, calendario y listado cronológico, o un Excel de dos hojas con el detalle de turnos y el resumen horario para RRHH.',
    },
    {
        icon: CalendarClock,
        accent: 'indigo',
        title: 'Google Calendar (OAuth2) y suscripción .ics',
        description:
            'Cada profesional sincroniza sus guardias automáticamente en Google Calendar o mediante enlace .ics en Apple Calendar y Outlook, manteniendo siempre las permutas actualizadas.',
    },
];

const steps = [
    {
        number: '01',
        icon: SlidersHorizontal,
        title: 'Configura tu equipo y reglas',
        description:
            'Da de alta al personal (adjuntos y/o residentes), festivos propios del servicio, vacaciones, rotaciones y restricciones individuales.',
    },
    {
        number: '02',
        icon: Sparkles,
        title: 'Simula, genera y ajusta',
        description:
            'Ejecuta una simulación previa (Dry-Run) para comprobar la equidad, genera el cuadrante y realiza ajustes manuales con alertas de conflicto en tiempo real.',
    },
    {
        number: '03',
        icon: CalendarCheck2,
        title: 'Exporta y comparte con el servicio',
        description:
            'Descarga el PDF para el grupo de WhatsApp o el tablón, entrega el Excel de horas a Nóminas y deja que cada compañero reciba sus turnos en su calendario.',
    },
];
</script>

<template>
    <Head title="Planificación inteligente de guardias hospitalarias" />

    <div
        class="min-h-screen bg-zinc-950 text-zinc-100 font-sans antialiased selection:bg-emerald-500 selection:text-zinc-950"
    >
        <!-- 1. NAVBAR FIJA Y LIMPIA -->
        <header
            class="fixed inset-x-0 top-0 z-50 border-b border-zinc-800/80 bg-zinc-950/85 backdrop-blur-md"
        >
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 shadow-lg shadow-emerald-500/10"
                    >
                        <AppLogoIcon class="h-5 w-5" />
                    </div>
                    <span class="text-xl font-black tracking-tight text-white">
                        Clini<span class="text-emerald-400">Core</span>
                    </span>
                </Link>

                <!-- Enlaces desktop -->
                <nav class="hidden items-center gap-7 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-zinc-400 transition-colors hover:text-white"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <!-- Acciones desktop -->
                <div v-if="canLogin" class="hidden items-center gap-3 md:flex">
                    <Link
                        v-if="isAuthenticated"
                        href="/dashboard"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400"
                    >
                        Ir al panel
                        <ArrowRight class="h-4 w-4" />
                    </Link>

                    <template v-else>
                        <Link
                            href="/login"
                            class="rounded-xl px-3.5 py-2 text-sm font-semibold text-zinc-300 transition-colors hover:bg-zinc-900 hover:text-white"
                        >
                            Iniciar sesión
                        </Link>
                        <a
                            :href="accessRequestMailto"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400"
                        >
                            Solicitar acceso
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </template>
                </div>

                <!-- Botón menú móvil -->
                <button
                    type="button"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white md:hidden"
                    aria-label="Abrir menú de navegación"
                >
                    <X v-if="mobileMenuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <!-- Drawer móvil -->
            <div
                v-if="mobileMenuOpen"
                class="border-t border-zinc-800 bg-zinc-950 px-4 py-5 md:hidden"
            >
                <div class="flex flex-col space-y-3">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        @click="mobileMenuOpen = false"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-300 hover:bg-zinc-900 hover:text-white"
                    >
                        {{ link.label }}
                    </a>

                    <div v-if="canLogin" class="mt-2 flex flex-col gap-2.5 border-t border-zinc-800 pt-4">
                        <Link
                            v-if="isAuthenticated"
                            href="/dashboard"
                            class="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-zinc-950"
                        >
                            Ir al panel
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <template v-else>
                            <Link
                                href="/login"
                                class="rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-2.5 text-center text-sm font-semibold text-zinc-200"
                            >
                                Iniciar sesión
                            </Link>
                            <a
                                :href="accessRequestMailto"
                                class="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-zinc-950"
                            >
                                Solicitar acceso
                                <ArrowRight class="h-4 w-4" />
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <!-- 2. HERO SECTION -->
            <section class="relative overflow-hidden px-4 pt-28 pb-16 sm:px-6 sm:pt-36 sm:pb-24">
                <div
                    class="pointer-events-none absolute top-16 left-1/2 -z-10 h-[360px] w-[680px] -translate-x-1/2 rounded-full bg-gradient-to-tr from-emerald-600/20 via-teal-500/10 to-transparent blur-[130px]"
                ></div>

                <div class="mx-auto max-w-4xl text-center">
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-1.5 text-xs font-semibold text-emerald-300"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Para servicios de Medicina, Farmacia Hospitalaria, Enfermería y más
                    </div>

                    <h1
                        class="text-4xl font-black tracking-tight text-white sm:text-5xl lg:text-6xl lg:leading-[1.08]"
                    >
                        Genera el cuadrante de guardias de tu servicio
                        <span
                            class="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-500 bg-clip-text text-transparent"
                        >
                            sin pelearte con Excel
                        </span>
                    </h1>

                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-zinc-400 sm:text-lg"
                    >
                        Ahorra horas cada mes organizando guardias de adjuntos y residentes con equidad anual real, respeto estricto de salientes, festivos y bajas, y exportación lista para WhatsApp y Nóminas.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                        <Link
                            v-if="isAuthenticated"
                            href="/dashboard"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-7 py-3.5 text-sm font-black text-zinc-950 shadow-xl shadow-emerald-500/20 transition-all hover:bg-emerald-400 sm:w-auto sm:text-base"
                        >
                            Entrar al panel
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <a
                            v-else
                            :href="accessRequestMailto"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-7 py-3.5 text-sm font-black text-zinc-950 shadow-xl shadow-emerald-500/20 transition-all hover:bg-emerald-400 sm:w-auto sm:text-base"
                        >
                            Solicitar acceso gratis
                            <ArrowRight class="h-4 w-4" />
                        </a>
                        <a
                            href="#demo"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-zinc-900/90 px-6 py-3.5 text-sm font-bold text-zinc-200 transition-all hover:border-zinc-700 hover:bg-zinc-900 hover:text-white sm:w-auto sm:text-base"
                        >
                            <Play class="h-4 w-4 text-emerald-400" />
                            Ver demo interactiva
                        </a>
                    </div>

                    <p class="mt-4 text-xs text-zinc-500">
                        Sin tarjeta · Sin instalación hospitalaria · Listo en minutos
                    </p>
                </div>

                <!-- Preview interactivo / Vídeo 60s -->
                <div class="mt-12 sm:mt-16">
                    <LandingCuadrantePreview />
                </div>
            </section>

            <!-- 3. PRUEBA SOCIAL SOBRIA Y CREÍBLE -->
            <section class="border-y border-zinc-800/80 bg-zinc-900/40 px-4 py-8 sm:px-6">
                <div
                    class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 text-center md:flex-row md:text-left"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-500/25 bg-emerald-500/10 text-emerald-400"
                        >
                            <Building2 class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">
                                Ya utilizado en servicios reales de Medicina y Farmacia Hospitalaria
                            </p>
                            <p class="text-xs text-zinc-400">
                                Diseñado junto a profesionales hospitalarios en España para cualquier unidad con turnos de guardia.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-2 font-mono text-xs text-zinc-400">
                        <span class="rounded-lg border border-zinc-800 bg-zinc-950/80 px-3 py-1.5">Medicina</span>
                        <span class="rounded-lg border border-zinc-800 bg-zinc-950/80 px-3 py-1.5">Farmacia Hospitalaria</span>
                        <span class="rounded-lg border border-zinc-800 bg-zinc-950/80 px-3 py-1.5">Enfermería</span>
                        <span class="rounded-lg border border-zinc-800 bg-zinc-950/80 px-3 py-1.5">Urgencias / UCI</span>
                    </div>
                </div>
            </section>

            <!-- 4. EL PROBLEMA VS. LA SOLUCIÓN -->
            <section class="px-4 py-20 sm:px-6 sm:py-24">
                <div class="mx-auto max-w-6xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-400">
                            Adiós al caos mensual
                        </span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            De cuadrantes artesanales en Excel a un reparto justo y trazable
                        </h2>
                        <p class="mt-3 text-sm text-zinc-400 sm:text-base">
                            Quien coordina las guardias no debería perder un fin de semana cada mes resolviendo encajes imposibles.
                        </p>
                    </div>

                    <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Antes -->
                        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 sm:p-8">
                            <div class="mb-5 flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-400">
                                    <XCircle class="h-4.5 w-4.5" />
                                </span>
                                <h3 class="text-lg font-bold text-zinc-200">Con Excel y WhatsApp</h3>
                            </div>
                            <ul class="space-y-3.5">
                                <li
                                    v-for="item in painPoints"
                                    :key="item"
                                    class="flex items-start gap-3 text-sm text-zinc-400"
                                >
                                    <XCircle class="mt-0.5 h-4 w-4 shrink-0 text-rose-400/80" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Después -->
                        <div
                            class="rounded-2xl border border-emerald-500/30 bg-gradient-to-b from-emerald-950/25 via-zinc-900/80 to-zinc-900/80 p-6 shadow-xl shadow-emerald-950/20 sm:p-8"
                        >
                            <div class="mb-5 flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400">
                                    <CheckCircle2 class="h-4.5 w-4.5" />
                                </span>
                                <h3 class="text-lg font-bold text-white">Con CliniCore</h3>
                            </div>
                            <ul class="space-y-3.5">
                                <li
                                    v-for="item in solutionPoints"
                                    :key="item"
                                    class="flex items-start gap-3 text-sm text-zinc-200"
                                >
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. FUNCIONALIDADES CLAVE (REALES DEL CÓDIGO) -->
            <section
                id="funcionalidades"
                class="border-t border-zinc-800/80 bg-zinc-900/30 px-4 py-20 sm:px-6 sm:py-24"
            >
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-400">
                            Funcionalidades reales
                        </span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Todo lo que necesita tu servicio para cerrar el mes sin fricciones
                        </h2>
                        <p class="mt-3 text-sm text-zinc-400 sm:text-base">
                            Construido específicamente para las casuísticas de los hospitales en España, sin funciones de relleno.
                        </p>
                    </div>

                    <div class="mt-14 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feat in features"
                            :key="feat.title"
                            class="group rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 transition-all hover:border-zinc-700 hover:bg-zinc-900"
                        >
                            <div
                                class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl border transition-transform group-hover:scale-105"
                                :class="{
                                    'border-emerald-500/25 bg-emerald-500/10 text-emerald-400': feat.accent === 'emerald',
                                    'border-teal-500/25 bg-teal-500/10 text-teal-400': feat.accent === 'teal',
                                    'border-purple-500/25 bg-purple-500/10 text-purple-400': feat.accent === 'purple',
                                    'border-amber-500/25 bg-amber-500/10 text-amber-400': feat.accent === 'amber',
                                    'border-sky-500/25 bg-sky-500/10 text-sky-400': feat.accent === 'sky',
                                    'border-indigo-500/25 bg-indigo-500/10 text-indigo-400': feat.accent === 'indigo',
                                }"
                            >
                                <component :is="feat.icon" class="h-5 w-5" />
                            </div>
                            <h3 class="text-base font-bold tracking-tight text-white sm:text-lg">
                                {{ feat.title }}
                            </h3>
                            <p class="mt-2.5 text-sm leading-relaxed text-zinc-400">
                                {{ feat.description }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- 6. CÓMO FUNCIONA (3 PASOS) -->
            <section id="como-funciona" class="border-t border-zinc-800/80 px-4 py-20 sm:px-6 sm:py-24">
                <div class="mx-auto max-w-6xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-400">
                            Flujo en 3 pasos
                        </span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Pasa de horas de encaje a publicar el cuadrante en minutos
                        </h2>
                    </div>

                    <div class="mt-14 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div
                            v-for="step in steps"
                            :key="step.number"
                            class="relative rounded-2xl border border-zinc-800 bg-zinc-900/60 p-6 sm:p-7"
                        >
                            <div class="mb-5 flex items-center justify-between">
                                <span
                                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-500/25 bg-emerald-500/10 text-emerald-400"
                                >
                                    <component :is="step.icon" class="h-5 w-5" />
                                </span>
                                <span class="font-mono text-2xl font-black text-zinc-800">
                                    {{ step.number }}
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-white">{{ step.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-400">
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 7. PLANES / ACCESO (CONFIGURABLE EN landing.ts) -->
            <section
                id="planes"
                class="border-t border-zinc-800/80 bg-zinc-900/30 px-4 py-20 sm:px-6 sm:py-24"
            >
                <div class="mx-auto max-w-5xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-400">
                            Planes y acceso
                        </span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Empieza hoy gratis con tu servicio
                        </h2>
                        <p class="mt-3 text-sm text-zinc-400 sm:text-base">
                            Actualmente CliniCore es gratuito durante el lanzamiento. Esta es nuestra estructura prevista para servicios que necesiten funciones avanzadas y soporte dedicado.
                        </p>

                        <!-- Toggle Mensual / Anual -->
                        <div
                            class="mt-7 inline-flex items-center rounded-xl border border-zinc-800 bg-zinc-950 p-1 text-xs font-semibold"
                        >
                            <button
                                type="button"
                                @click="billingCycle = 'monthly'"
                                class="cursor-pointer rounded-lg px-4 py-2 transition-all"
                                :class="
                                    billingCycle === 'monthly'
                                        ? 'bg-zinc-800 text-white'
                                        : 'text-zinc-400 hover:text-zinc-200'
                                "
                            >
                                Facturación mensual
                            </button>
                            <button
                                type="button"
                                @click="billingCycle = 'annual'"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-2 transition-all"
                                :class="
                                    billingCycle === 'annual'
                                        ? 'bg-emerald-500 text-zinc-950 font-bold'
                                        : 'text-zinc-400 hover:text-zinc-200'
                                "
                            >
                                Facturación anual
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="
                                        billingCycle === 'annual'
                                            ? 'bg-zinc-950/15 text-zinc-950'
                                            : 'bg-emerald-500/15 text-emerald-400'
                                    "
                                >
                                    -20%
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div
                            v-for="plan in PRICING_PLANS"
                            :key="plan.id"
                            class="flex flex-col justify-between rounded-2xl border p-7 sm:p-8"
                            :class="
                                plan.highlighted
                                    ? 'border-emerald-500/40 bg-gradient-to-b from-emerald-950/30 via-zinc-900 to-zinc-900 shadow-2xl shadow-emerald-950/20'
                                    : 'border-zinc-800 bg-zinc-900/70'
                            "
                        >
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-xl font-bold text-white">{{ plan.name }}</h3>
                                    <span
                                        v-if="plan.badge"
                                        class="rounded-full px-2.5 py-1 font-mono text-[11px] font-bold"
                                        :class="
                                            plan.highlighted
                                                ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-300'
                                                : 'border border-zinc-700 bg-zinc-800 text-zinc-300'
                                        "
                                    >
                                        {{ plan.badge }}
                                    </span>
                                </div>

                                <p class="mt-2.5 text-sm text-zinc-400">{{ plan.description }}</p>

                                <div class="mt-6 flex items-baseline gap-2">
                                    <span class="text-4xl font-black tracking-tight text-white">
                                        {{
                                            plan.customPriceLabel
                                                ? plan.customPriceLabel
                                                : `${billingCycle === 'annual' ? plan.annualMonthlyPrice : plan.monthlyPrice} €`
                                        }}
                                    </span>
                                    <span v-if="!plan.customPriceLabel" class="text-sm text-zinc-400">/ mes</span>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500">{{ plan.priceNote }}</p>

                                <ul class="mt-6 space-y-3 border-t border-zinc-800/90 pt-6">
                                    <li
                                        v-for="feat in plan.features"
                                        :key="feat"
                                        class="flex items-start gap-2.5 text-sm text-zinc-300"
                                    >
                                        <Check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                        <span>{{ feat }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-8">
                                <Link
                                    v-if="plan.ctaHref.startsWith('/')"
                                    :href="isAuthenticated ? '/dashboard' : plan.ctaHref"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition-all"
                                    :class="
                                        plan.highlighted
                                            ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400'
                                            : 'border border-zinc-700 bg-zinc-800 text-white hover:bg-zinc-700'
                                    "
                                >
                                    {{ isAuthenticated ? 'Ir a mi panel' : plan.ctaLabel }}
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                                <a
                                    v-else
                                    :href="plan.ctaHref"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-700 bg-zinc-800/90 px-5 py-3 text-sm font-bold text-white transition-all hover:bg-zinc-700"
                                >
                                    {{ plan.ctaLabel }}
                                    <Mail class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 8. PREGUNTAS FRECUENTES (FAQ) -->
            <section id="faq" class="border-t border-zinc-800/80 px-4 py-20 sm:px-6 sm:py-24">
                <div class="mx-auto max-w-3xl">
                    <div class="text-center">
                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-400">
                            Preguntas frecuentes
                        </span>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Dudas habituales antes de probarlo en tu unidad
                        </h2>
                    </div>

                    <div class="mt-12 space-y-3">
                        <div
                            v-for="(item, idx) in FAQ_ITEMS"
                            :key="item.question"
                            class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/60 transition-colors"
                            :class="openFaqIndex === idx ? 'border-emerald-500/35 bg-zinc-900' : ''"
                        >
                            <button
                                type="button"
                                @click="toggleFaq(idx)"
                                class="flex w-full cursor-pointer items-center justify-between gap-4 px-5 py-4 text-left sm:px-6 sm:py-5"
                                :aria-expanded="openFaqIndex === idx"
                            >
                                <span class="text-sm font-bold text-white sm:text-base">
                                    {{ item.question }}
                                </span>
                                <ChevronDown
                                    class="h-4.5 w-4.5 shrink-0 text-zinc-400 transition-transform duration-200"
                                    :class="openFaqIndex === idx ? 'rotate-180 text-emerald-400' : ''"
                                />
                            </button>
                            <div
                                v-if="openFaqIndex === idx"
                                class="border-t border-zinc-800/80 px-5 py-4 text-sm leading-relaxed text-zinc-400 sm:px-6"
                            >
                                {{ item.answer }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 9. CTA FINAL -->
            <section class="border-t border-zinc-800/80 px-4 py-20 sm:px-6">
                <div
                    class="relative mx-auto max-w-5xl overflow-hidden rounded-3xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/40 via-zinc-900 to-zinc-950 p-8 text-center shadow-2xl sm:p-14"
                >
                    <div
                        class="pointer-events-none absolute -top-20 left-1/2 h-56 w-96 -translate-x-1/2 rounded-full bg-emerald-500/15 blur-3xl"
                    ></div>

                    <div class="relative mx-auto max-w-2xl space-y-5">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 font-mono text-xs text-emerald-300"
                        >
                            <ShieldCheck class="h-3.5 w-3.5" />
                            Empieza el próximo mes sin Excel
                        </span>
                        <h2 class="text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Recupera tus tardes y garantiza un cuadrante justo para todo tu equipo
                        </h2>
                        <p class="text-sm text-zinc-400 sm:text-base">
                            Prueba CliniCore gratis en tu servicio o escríbenos si quieres que te ayudemos a configurar tus primeras reglas de guardia.
                        </p>
                        <div class="flex flex-col items-center justify-center gap-3 pt-2 sm:flex-row">
                            <Link
                                v-if="isAuthenticated"
                                href="/dashboard"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-7 py-3.5 text-sm font-black text-zinc-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 sm:w-auto"
                            >
                                Ir al panel
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                            <a
                                v-else
                                :href="accessRequestMailto"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-7 py-3.5 text-sm font-black text-zinc-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 sm:w-auto"
                            >
                                Solicitar acceso gratis
                                <ArrowRight class="h-4 w-4" />
                            </a>
                            <a
                                :href="`mailto:${PRODUCT_CONFIG.email}?subject=Consulta%20CliniCore%20para%20mi%20servicio`"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-700 bg-zinc-900/80 px-6 py-3.5 text-sm font-bold text-zinc-200 transition-colors hover:border-zinc-600 hover:text-white sm:w-auto"
                            >
                                <Mail class="h-4 w-4 text-emerald-400" />
                                {{ PRODUCT_CONFIG.email }}
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER LIMPIO -->
        <footer class="border-t border-zinc-800/80 bg-zinc-950 px-4 py-12 sm:px-6">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 sm:flex-row"
            >
                <div class="flex flex-col items-center gap-1.5 sm:items-start">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400"
                        >
                            <AppLogoIcon class="h-4 w-4" />
                        </div>
                        <span class="text-base font-black tracking-tight text-white">
                            Clini<span class="text-emerald-400">Core</span>
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500">
                        &copy; {{ new Date().getFullYear() }} {{ PRODUCT_CONFIG.name }} · {{ PRODUCT_CONFIG.domain }} · Todos los derechos reservados.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6 text-xs font-medium text-zinc-400">
                    <Link href="/privacidad" class="transition-colors hover:text-white">
                        Privacidad
                    </Link>
                    <Link href="/terminos" class="transition-colors hover:text-white">
                        Términos de uso
                    </Link>
                    <a
                        :href="`mailto:${PRODUCT_CONFIG.email}`"
                        class="transition-colors hover:text-emerald-400"
                    >
                        Contacto ({{ PRODUCT_CONFIG.email }})
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>
