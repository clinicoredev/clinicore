<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { 
    Activity, ShieldCheck, Cpu, Layers, CalendarDays, 
    Users, ArrowRight, CheckCircle2, Lock, Stethoscope, Sparkles 
} from '@lucide/vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Bienvenido a CliniCore — Inteligencia Operativa Sanitaria" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100 font-sans selection:bg-emerald-500 selection:text-zinc-950">
        
        <header class="fixed top-0 inset-x-0 z-50 bg-zinc-950/80 backdrop-blur-md border-b border-zinc-800/80">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/10">
                        <Activity class="w-6 h-6 animate-pulse" />
                    </div>
                    <span class="text-xl font-black tracking-tight text-white">Clini<span class="text-emerald-400">Core</span></span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-zinc-900 text-zinc-400 border border-zinc-800">SaaS v1.0</span>
                </div>

                <nav v-if="canLogin" class="flex items-center gap-4">
                    <Link 
                        v-if="$page.props.auth.user" 
                        href="/dashboard" 
                        class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-sm transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2"
                    >
                        Ir al Panel de Mando <ArrowRight class="w-4 h-4" />
                    </Link>

                    <template v-else>
                        <Link 
                            href="/login" 
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-zinc-300 hover:text-white hover:bg-zinc-900 transition-all"
                        >
                            Acceso Facultativos
                        </Link>

                        <Link 
                            v-if="canRegister" 
                            href="/register" 
                            class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-sm transition-all shadow-lg shadow-emerald-500/20 active:scale-95 cursor-pointer"
                        >
                            Alta de Servicio (Demo Gratuita)
                        </Link>
                    </template>
                </nav>

            </div>
        </header>

        <section class="relative pt-36 pb-24 px-6 overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-gradient-to-tr from-emerald-600/20 to-teal-500/10 blur-[130px] rounded-full pointer-events-none -z-10"></div>

            <div class="max-w-4xl mx-auto text-center space-y-6">
                
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-800 text-xs text-emerald-400 font-mono">
                    <Sparkles class="w-3.5 h-3.5" />
                    MOTOR ALGORÍTMICO VORAZ — TRIADO AUTOMÁTICO DE PLANTILLA
                </div>

                <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-none">
                    La ingeniería operativa que tu <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-500">Servicio Hospitalario</span> merece.
                </h1>

                <p class="text-base sm:text-lg text-zinc-400 max-w-2xl mx-auto font-normal leading-relaxed">
                    Elimina las hojas de cálculo inconexas. Planifica cuadrantes de guardias cumpliendo estrictamente los descansos de ley y gestiona casos clínicos bajo anonimización absoluta.
                </p>

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link 
                        v-if="canRegister"
                        href="/register" 
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black text-base transition-all shadow-xl shadow-emerald-500/20 flex items-center justify-center gap-3 cursor-pointer group active:scale-95"
                    >
                        Desplegar Nuevo Tenant
                        <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                    </Link>

                    <Link 
                        href="/login" 
                        class="w-full sm:w-auto px-8 py-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 font-bold text-base transition-all flex items-center justify-center gap-2"
                    >
                        <Lock class="w-4 h-4 text-zinc-500" />
                        Portal de Acceso Seguro
                    </Link>
                </div>

                <div class="pt-12 grid grid-cols-2 sm:grid-cols-3 gap-4 max-w-2xl mx-auto border-t border-zinc-800/80 text-left">
                    <div class="flex items-center gap-2.5 text-xs text-zinc-400 font-mono">
                        <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                        Cumplimiento RGPD
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-zinc-400 font-mono">
                        <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                        Base SQLite Aislada
                    </div>
                    <div class="col-span-2 sm:col-span-1 flex items-center justify-center sm:justify-start gap-2.5 text-xs text-zinc-400 font-mono">
                        <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                        Codificación CIE-10
                    </div>
                </div>

            </div>
        </section>

        <section class="py-20 px-6 bg-zinc-900/40 border-t border-b border-zinc-800/80">
            <div class="max-w-7xl mx-auto">
                
                <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                    <h2 class="text-xs font-mono uppercase tracking-widest text-emerald-400 font-bold">Arquitectura Desacoplada</h2>
                    <p class="text-3xl font-bold text-white tracking-tight">Diseñado por cirujanos del software para facultativos médicos</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <div class="p-8 rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 transition-all space-y-4 group">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20 group-hover:scale-110 transition-transform">
                            <Cpu class="w-6 h-6" />
                        </div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Planificador CSP Voraz</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed">
                            Resuelve el problema de asignación de turnos (*Nurse Rostering*) en milisegundos. Pondera equidad de fines de semana, vísperas y vetos personales de agenda de forma 100% matemática.
                        </p>
                    </div>

                    <div class="p-8 rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 transition-all space-y-4 group">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20 group-hover:scale-110 transition-transform">
                            <Layers class="w-6 h-6" />
                        </div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Sala de Control Roster</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed">
                            Fusión en tiempo real de cuadrantes de urgencias y solicitudes de ausencias autorizadas. Visualiza colisiones y tria la capacidad operativa de tu planta en una sola pantalla.
                        </p>
                    </div>

                    <div class="p-8 rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 transition-all space-y-4 group">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center border border-purple-500/20 group-hover:scale-110 transition-transform">
                            <ShieldCheck class="w-6 h-6" />
                        </div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Privacidad por Diseño</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed">
                            Aislamiento estricto de identificadores personales. Limpieza automática de metadatos EXIF en radiografías y control de acceso jerárquico basado en roles (RBAC).
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <footer class="py-12 px-6 text-center text-xs font-mono text-zinc-600 space-y-2">
            <p>Trabajo de Fin de Grado — Grado en Ingeniería Informática</p>
            <p class="text-zinc-500">CliniCore Plataforma Sanitaria &copy; {{ new Date().getFullYear() }} — Todos los derechos reservados bajo licencia comercial.</p>
        </footer>

    </div>
</template>