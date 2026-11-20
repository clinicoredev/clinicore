<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Activity, Users, CalendarDays, Clock,
    Stethoscope, CheckCircle2, XCircle, AlertCircle,
    ArrowRight, ShieldCheck, Zap, CalendarClock, PartyPopper,
    Moon, Copy, Smartphone, Monitor, Info, X, GraduationCap
} from '@lucide/vue';

const props = defineProps({
    kpis: Object,
    desglose_anual: Array,
    guardias_hoy: Object,
    guardia_hoy: Object,
    mi_proxima_guardia: Object,
    cola_firmas: Array,
    token_calendario: String,
    gcal_sync: Object,
    cumplimiento_trimestral_48h: Object,
    permisos: Object,
});

const page = usePage();
const usuarioActual = page.props.auth.user;
const nombreUsuario = usuarioActual.name;

const esRevisor = computed(() => Boolean(props.permisos?.puede_revisar_ausencias || props.permisos?.es_jefe || props.permisos?.es_tutor));

const guardiasHoyAdjuntos = computed(() => props.guardias_hoy?.adjuntos ?? []);
const guardiasHoyResidentes = computed(() => props.guardias_hoy?.residentes ?? []);
const hayGuardiasHoy = computed(() => guardiasHoyAdjuntos.value.length > 0 || guardiasHoyResidentes.value.length > 0 || Boolean(props.guardia_hoy));

const filtroColectivoAnual = ref('todos');

const desgloseFiltrado = computed(() => {
    const lista = props.desglose_anual ?? [];
    if (filtroColectivoAnual.value === 'todos') {
        return lista;
    }
    return lista.filter(d => (d.colectivo ?? 'adjuntos') === filtroColectivoAnual.value);
});

const misEstadisticas = computed(() => {
    return (props.desglose_anual ?? []).find(d => d.id === usuarioActual.id) || {
        total_guardias: 0, guardias_17h: 0, guardias_24h_finde: 0, guardias_24h_festivo: 0, horas_totales: 0
    };
});

const modalCalendarioAbierto = ref(false);
const linkCopiado = ref(false);

const formGcalSync = useForm({
    gcal_sync_enabled: Boolean(props.gcal_sync?.enabled),
    gcal_calendar_id: props.gcal_sync?.calendar_id || 'primary',
    sincronizar_ahora: true,
});

const guardarConfigSyncGcal = () => {
    formGcalSync.post('/calendario/sync-config', {
        preserveScroll: true,
    });
};

const desconectarOauthGcal = () => {
    router.delete('/calendario/oauth/disconnect', {
        preserveScroll: true,
    });
};

const firmarDesdePortada = (id, nuevoEstado) => {
    router.patch(`/ausencias/${id}/resolver`, { estado: nuevoEstado }, { preserveScroll: true });
};

const urlCalendario = computed(() => {
    return `${window.location.origin}/feed/calendario/${props.token_calendario}.ics`;
});

const copiarEnlaceCalendario = async () => {
    try {
        await navigator.clipboard.writeText(urlCalendario.value);
        linkCopiado.value = true;
        setTimeout(() => linkCopiado.value = false, 3000);
    } catch {
        const textArea = document.createElement("textarea");
        textArea.value = urlCalendario.value;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        linkCopiado.value = true;
        setTimeout(() => linkCopiado.value = false, 3000);
    }
};
</script>

<template>
    <Head title="Panel de Mando" />

    <div class="space-y-8 animate-in fade-in duration-300">

        <!-- CABECERA -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-800/80 pb-6">
            <div>
                <div class="flex items-center gap-2 text-xs font-mono text-emerald-400 mb-1 uppercase tracking-wider font-bold">
                    <Zap class="w-3.5 h-3.5 fill-emerald-400" />
                    {{ $page.props.auth.user.hospital }} — {{ $page.props.auth.user.especialidad }}
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">
                    Buenos días, <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-200">{{ nombreUsuario }}</span>
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <span v-if="permisos.es_jefe" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-900 border border-zinc-800 text-xs text-zinc-300 font-mono">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Jefatura: Autorizado
                </span>
                <span v-else-if="permisos.es_tutor" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-900 border border-indigo-500/40 text-xs text-indigo-300 font-mono">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    Tutoría de Residentes: Autorizado
                </span>
            </div>
        </div>

        <!-- KPIs DINÁMICOS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden group transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ permisos.es_jefe ? 'Plantilla Activa' : 'Mi Carga (Mes)' }}
                    </span>
                    <div class="p-2 rounded-xl bg-blue-500/10 text-blue-400">
                        <Users v-if="permisos.es_jefe" class="w-4 h-4" />
                        <Activity v-else class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white font-mono">
                        {{ permisos.es_jefe ? kpis.total_medicos : kpis.mis_guardias_mes }}
                    </span>
                    <span v-if="permisos.es_jefe" class="text-xs text-zinc-500">/ {{ kpis.limite_plan }} plazas</span>
                    <span v-else class="text-xs text-zinc-500">turnos este mes</span>
                </div>
            </div>

            <div class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden group transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ permisos.es_jefe ? 'Guardias Dpto.' : 'Estado Legal' }}
                    </span>
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                        <CalendarDays v-if="permisos.es_jefe" class="w-4 h-4" />
                        <ShieldCheck v-else class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-white font-mono" v-if="permisos.es_jefe">
                        {{ kpis.guardias_mes_servicio }}
                    </span>
                    <span class="text-lg font-black text-white" v-else>Cumplimiento</span>
                </div>
                <p class="mt-3 text-[11px] text-emerald-400/80 font-medium">
                    {{ permisos.es_jefe ? 'Cuadrante en curso' : 'Descansos post-guardia OK' }}
                </p>
            </div>

            <div class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden group transition-all" :class="kpis.ausencias_pendientes > 0 ? 'border-amber-500/30' : ''">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider" :class="kpis.ausencias_pendientes > 0 ? 'text-amber-400' : 'text-zinc-400'">
                        {{ esRevisor ? 'Firmas Pendientes' : 'Mis Peticiones' }}
                    </span>
                    <div class="p-2 rounded-xl text-amber-400" :class="kpis.ausencias_pendientes > 0 ? 'bg-amber-500/20' : 'bg-amber-500/10'">
                        <Clock class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black font-mono" :class="kpis.ausencias_pendientes > 0 ? 'text-amber-400' : 'text-white'">
                        {{ kpis.ausencias_pendientes }}
                    </span>
                    <span class="text-xs text-zinc-500 ml-1">en espera</span>
                </div>
            </div>

            <div v-if="!permisos.es_jefe && mi_proxima_guardia" class="p-5 bg-gradient-to-br from-indigo-950 to-zinc-900 border border-indigo-500/30 rounded-2xl relative overflow-hidden transition-all shadow-lg shadow-indigo-500/10">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-300">Próximo Turno</span>
                    <div class="p-2 rounded-xl bg-indigo-500/20 text-indigo-400"><CalendarClock class="w-4 h-4" /></div>
                </div>
                <div class="mt-4">
                    <span class="block text-sm font-bold text-white mb-0.5">{{ mi_proxima_guardia.fecha_legible }}</span>
                    <span class="text-xs text-indigo-300">{{ mi_proxima_guardia.tipo }}</span>
                </div>
                <div class="absolute bottom-0 right-0 p-3 opacity-20">
                    <span class="text-6xl font-black">{{ mi_proxima_guardia.dias_faltan }}<span class="text-2xl">d</span></span>
                </div>
            </div>

            <div v-else-if="!permisos.es_jefe" class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Próximo Turno</span>
                </div>
                <div class="mt-4 text-xs text-zinc-500">Sin guardias asignadas próximamente.</div>
            </div>

            <div v-if="permisos.es_jefe" class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Incidencias Hoy</span>
                    <div class="p-2 rounded-xl bg-zinc-800 text-zinc-500"><AlertCircle class="w-4 h-4" /></div>
                </div>
                <div class="mt-4 text-3xl font-black text-zinc-600 font-mono">0</div>
            </div>

            <!-- Widget Suscripción Calendario Mejorado -->
            <div class="mt-4 p-4 rounded-xl border border-indigo-500/30 bg-indigo-500/10 flex items-center justify-between gap-4 group hover:bg-indigo-500/20 transition-colors cursor-pointer" @click="modalCalendarioAbierto = true">
                <div>
                    <h4 class="text-xs font-bold text-indigo-300 flex items-center gap-1.5"><Smartphone class="w-3.5 h-3.5" /> Sincronización Automática</h4>
                    <p class="text-[10px] text-indigo-400/70 mt-0.5">Lleva tus turnos en tu móvil personal</p>
                </div>
                <div class="p-2 bg-indigo-500/20 rounded-lg text-indigo-400 group-hover:bg-indigo-500 group-hover:text-zinc-950 transition-colors">
                    <ArrowRight class="w-4 h-4" />
                </div>
            </div>

        </div>

        <!-- ÁREA PRINCIPAL -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- PANEL CENTRAL: EQUIPO DE GUARDIA POR COLECTIVO -->
            <div class="lg:col-span-2 bg-gradient-to-br from-zinc-900 to-zinc-950 border border-zinc-800 rounded-3xl p-6 shadow-2xl flex flex-col justify-between">

                <div class="flex items-center justify-between border-b border-zinc-800/80 pb-4">
                    <div class="flex items-center gap-2">
                        <Stethoscope class="w-5 h-5 text-emerald-400" />
                        <h2 class="font-bold text-white tracking-wide">Equipo de Guardia — Hoy</h2>
                    </div>
                    <span class="text-xs font-mono text-zinc-500">{{ new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }) }}</span>
                </div>

                <div v-if="hayGuardiasHoy" class="my-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Colectivo Adjuntos -->
                    <div class="p-5 rounded-2xl bg-zinc-900/60 border border-emerald-500/30 flex flex-col gap-3">
                        <div class="flex items-center justify-between border-b border-zinc-800 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                                <Stethoscope class="w-3.5 h-3.5" /> Facultativos Adjuntos
                            </span>
                            <span class="text-[10px] font-mono text-zinc-500">{{ guardiasHoyAdjuntos.length }} en turno</span>
                        </div>

                        <div v-if="guardiasHoyAdjuntos.length > 0" class="space-y-3">
                            <div
                                v-for="g in guardiasHoyAdjuntos"
                                :key="g.id"
                                class="p-3 rounded-xl bg-zinc-950/80 border border-zinc-800/80 flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500 flex items-center justify-center text-xs font-black text-emerald-400 shrink-0">
                                        {{ g.medico.slice(0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <span
                                            class="px-2 py-0.5 rounded text-[9px] font-mono font-black uppercase tracking-wider"
                                            :class="g.es_especial ? 'bg-rose-500 text-white' : 'bg-emerald-500 text-zinc-950'"
                                        >
                                            {{ g.tipo }}
                                        </span>
                                        <h3 class="text-sm font-bold text-white mt-1 truncate">{{ g.medico }}</h3>
                                        <p class="text-[11px] text-zinc-400 font-mono truncate">{{ g.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-6 text-center text-xs text-zinc-500 italic">
                            Sin adjuntos asignados hoy.
                        </div>
                    </div>

                    <!-- Colectivo Residentes -->
                    <div class="p-5 rounded-2xl bg-zinc-900/60 border border-indigo-500/30 flex flex-col gap-3">
                        <div class="flex items-center justify-between border-b border-zinc-800 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-400 flex items-center gap-1.5">
                                <GraduationCap class="w-3.5 h-3.5" /> Residentes (MIR)
                            </span>
                            <span class="text-[10px] font-mono text-zinc-500">{{ guardiasHoyResidentes.length }} en turno</span>
                        </div>

                        <div v-if="guardiasHoyResidentes.length > 0" class="space-y-3">
                            <div
                                v-for="g in guardiasHoyResidentes"
                                :key="g.id"
                                class="p-3 rounded-xl bg-zinc-950/80 border border-zinc-800/80 flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-full bg-indigo-500/10 border border-indigo-500 flex items-center justify-center text-xs font-black text-indigo-400 shrink-0">
                                        {{ g.medico.slice(0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <span
                                            class="px-2 py-0.5 rounded text-[9px] font-mono font-black uppercase tracking-wider"
                                            :class="g.es_especial ? 'bg-rose-500 text-white' : 'bg-indigo-500 text-white'"
                                        >
                                            {{ g.tipo }}
                                        </span>
                                        <h3 class="text-sm font-bold text-white mt-1 truncate">{{ g.medico }}</h3>
                                        <p class="text-[11px] text-zinc-400 font-mono truncate">{{ g.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-6 text-center text-xs text-zinc-500 italic">
                            Sin residentes asignados hoy.
                        </div>
                    </div>
                </div>

                <div v-else class="my-12 text-center py-8 border border-dashed border-zinc-800 rounded-2xl">
                    <CheckCircle2 class="w-8 h-8 text-zinc-600 mx-auto mb-2" />
                    <p class="text-sm font-bold text-zinc-400">Servicio cubierto por plantilla ordinaria</p>
                    <p class="text-xs text-zinc-600 mt-1">No hay turnos de guardia 17h/24h registrados para hoy.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-4 border-t border-zinc-800/80">
                    <button @click="router.get('/calendario-completo')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Ver Calendario General
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-emerald-400 transition-all" />
                    </button>

                    <button v-if="permisos.es_jefe" @click="router.get('/guardias')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Gestionar Cuadrante Adjuntos
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-emerald-400 transition-all" />
                    </button>

                    <button v-else-if="permisos.puede_gestionar_residentes" @click="router.get('/residentes/guardias')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Gestionar Cuadrante Residentes
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-indigo-400 transition-all" />
                    </button>

                    <button v-else @click="router.get('/ausencias')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Solicitar Día Libre / Permiso
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-emerald-400 transition-all" />
                    </button>
                </div>
            </div>

            <!-- COLUMNA DERECHA: BANDEJA DE TRÁMITES -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-zinc-800 pb-4 mb-4">
                        <h2 class="font-bold text-white text-sm">
                            {{ esRevisor ? 'Despacho — Por Firmar' : 'Mis Trámites Recientes' }}
                        </h2>
                        <span v-if="esRevisor && kpis.ausencias_pendientes > 0" class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    </div>

                    <div v-if="cola_firmas.length === 0" class="text-center py-12">
                        <CheckCircle2 class="w-10 h-10 text-emerald-500/40 mx-auto mb-2" />
                        <p class="text-xs font-bold text-zinc-400">Bandeja limpia</p>
                        <p class="text-[11px] text-zinc-600 mt-0.5">No hay documentos en curso.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in cola_firmas" :key="item.id" class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800/80 flex flex-col gap-2.5 relative overflow-hidden">
                            <div v-if="!esRevisor && item.estado === 'aprobada'" class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500"></div>
                            <div v-if="!esRevisor && item.estado === 'denegada'" class="absolute left-0 top-0 bottom-0 w-1 bg-rose-500"></div>
                            <div v-if="!esRevisor && item.estado === 'pendiente'" class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500"></div>

                            <div class="flex justify-between items-start">
                                <div :class="!esRevisor ? 'pl-2' : ''">
                                    <span v-if="esRevisor" class="text-xs font-bold text-emerald-400">{{ item.medico }}</span>
                                    <span class="block text-[11px] text-zinc-300">{{ item.tipo }}</span>
                                    <span class="text-[10px] font-mono text-zinc-500">{{ item.fechas }}</span>
                                </div>

                                <div v-if="esRevisor" class="flex gap-1.5 shrink-0">
                                    <button @click="firmarDesdePortada(item.id, 'aprobada')" title="Autorizar" class="p-1.5 bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-zinc-950 rounded-lg border border-emerald-500/30 transition-all cursor-pointer">
                                        <CheckCircle2 class="w-4 h-4" />
                                    </button>
                                    <button @click="firmarDesdePortada(item.id, 'denegada')" title="Denegar" class="p-1.5 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white rounded-lg border border-rose-500/30 transition-all cursor-pointer">
                                        <XCircle class="w-4 h-4" />
                                    </button>
                                </div>

                                <div v-else>
                                    <span v-if="item.estado === 'pendiente'" class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase">En revisión</span>
                                    <span v-if="item.estado === 'aprobada'" class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">Aprobado</span>
                                    <span v-if="item.estado === 'denegada'" class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase">Denegado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-zinc-800 text-center">
                    <button @click="router.get('/ausencias')" class="text-xs text-zinc-500 hover:text-white transition-colors cursor-pointer">
                        Ver registro completo &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- MÓDULO ESTADÍSTICAS YTD (PARA TODOS: TRANSPARENCIA POR COLECTIVO) -->
        <div class="mt-8 p-6 bg-zinc-900 border border-zinc-800 rounded-2xl shadow-xl space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <Activity class="w-5 h-5 text-emerald-400" />
                        Transparencia de Equidad (YTD)
                    </h3>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        Balance estadístico anual diferenciado por colectivo (Adjuntos y Residentes).
                    </p>
                </div>

                <!-- Selector de Colectivo -->
                <div class="inline-flex rounded-xl bg-zinc-950 p-1 border border-zinc-800">
                    <button
                        @click="filtroColectivoAnual = 'todos'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="filtroColectivoAnual === 'todos' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white'"
                    >
                        Todos
                    </button>
                    <button
                        @click="filtroColectivoAnual = 'adjuntos'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="filtroColectivoAnual === 'adjuntos' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-zinc-400 hover:text-white'"
                    >
                        Adjuntos
                    </button>
                    <button
                        @click="filtroColectivoAnual = 'residentes'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="filtroColectivoAnual === 'residentes' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'text-zinc-400 hover:text-white'"
                    >
                        Residentes
                    </button>
                </div>
            </div>

            <!-- TARJETAS DE DESGLOSE INDIVIDUAL -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-zinc-500">Tus Guardias Totales</span>
                        <span class="block text-2xl font-black font-mono text-white mt-1">{{ misEstadisticas.total_guardias }}</span>
                        <span class="block text-[10px] font-mono text-emerald-400 mt-0.5">{{ misEstadisticas.horas_totales ?? 0 }}h acumuladas</span>
                    </div>
                    <div class="p-2 bg-emerald-500/10 rounded-lg"><CalendarDays class="w-5 h-5 text-emerald-400" /></div>
                </div>

                <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-zinc-500">Ordinarias (17h)</span>
                        <span class="block text-2xl font-black font-mono text-zinc-300 mt-1">{{ misEstadisticas.guardias_17h }}</span>
                    </div>
                    <div class="p-2 bg-zinc-800 rounded-lg"><Moon class="w-5 h-5 text-zinc-400" /></div>
                </div>

                <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-zinc-500">Fines de Semana (24h)</span>
                        <span class="block text-2xl font-black font-mono text-amber-400 mt-1">{{ misEstadisticas.guardias_24h_finde }}</span>
                    </div>
                    <div class="p-2 bg-amber-500/10 rounded-lg"><CalendarClock class="w-5 h-5 text-amber-400" /></div>
                </div>

                <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-zinc-500">Festivos (24h)</span>
                        <span class="block text-2xl font-black font-mono text-rose-400 mt-1">{{ misEstadisticas.guardias_24h_festivo }}</span>
                    </div>
                    <div class="p-2 bg-rose-500/10 rounded-lg"><PartyPopper class="w-5 h-5 text-rose-400" /></div>
                </div>

                <div v-if="cumplimiento_trimestral_48h" class="bg-zinc-950 p-4 rounded-xl border border-indigo-500/30 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-indigo-300">
                            Media Semanal ({{ cumplimiento_trimestral_48h.trimestre_etiqueta }})
                        </span>
                        <span class="block text-2xl font-black font-mono text-indigo-300 mt-1">
                            {{ cumplimiento_trimestral_48h.mi_promedio_semanal }}h/sem
                        </span>
                        <span class="block text-[10px] font-mono text-zinc-400 mt-0.5">
                            Pico: {{ cumplimiento_trimestral_48h.mi_pico_semanal }}h · Ref. Directiva 48h
                        </span>
                    </div>
                    <div class="p-2 bg-indigo-500/10 rounded-lg"><ShieldCheck class="w-5 h-5 text-indigo-400" /></div>
                </div>
            </div>

            <!-- TABLA GLOBAL DE EQUIDAD DIFERENCIADA -->
            <div class="overflow-x-auto rounded-xl border border-zinc-800 relative max-h-[400px]">
                <table class="w-full text-left border-collapse min-w-[750px] relative">
                    <thead class="sticky top-0 z-10 bg-zinc-950/90 backdrop-blur-sm">
                        <tr class="text-zinc-400 text-[10px] uppercase font-bold tracking-wider border-b border-zinc-800">
                            <th class="py-3 px-4">Facultativo</th>
                            <th class="py-3 px-4 text-center">Colectivo / Rol</th>
                            <th class="py-3 px-4 text-center">Diarias (17h)</th>
                            <th class="py-3 px-4 text-center">Fines de Sem. (24h)</th>
                            <th class="py-3 px-4 text-center">Festivos (24h)</th>
                            <th class="py-3 px-4 text-center">Horas Año</th>
                            <th class="py-3 px-4 text-center text-emerald-400">Total Guardias</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-sm">
                        <tr
                            v-for="stats in desgloseFiltrado"
                            :key="stats.id"
                            class="transition-colors"
                            :class="stats.id === usuarioActual.id ? 'bg-emerald-500/10 border-l-2 border-emerald-500' : 'hover:bg-zinc-800/30'"
                        >
                            <td class="py-3 px-4 font-semibold flex items-center gap-2">
                                <span :class="stats.id === usuarioActual.id ? 'text-emerald-400' : 'text-white'">
                                    {{ stats.nombre.replace('Dr. ', '').replace('Dra. ', '') }}
                                </span>
                                <span v-if="stats.id === usuarioActual.id" class="px-2 py-0.5 rounded text-[9px] bg-emerald-500 text-zinc-950 font-black uppercase">Tú</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                    :class="stats.colectivo === 'residentes'
                                        ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'
                                        : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'"
                                >
                                    {{ stats.rol ?? (stats.colectivo === 'residentes' ? 'Residente' : 'Adjunto') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-zinc-300">{{ stats.guardias_17h }}</td>
                            <td class="py-3 px-4 text-center font-mono text-amber-400">{{ stats.guardias_24h_finde }}</td>
                            <td class="py-3 px-4 text-center font-mono text-rose-400">
                                {{ stats.guardias_24h_festivo }}
                                <span v-if="stats.guardias_24h_especial > 0" class="text-[10px] text-rose-300 ml-1" title="Festivos Especiales">
                                    (★{{ stats.guardias_24h_especial }})
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-zinc-300">
                                {{ stats.horas_totales ?? 0 }}h
                                <span v-if="stats.guardias_localizadas > 0" class="text-[10px] text-indigo-300 ml-1">
                                    ({{ stats.guardias_localizadas }} Loc.)
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-emerald-400 bg-emerald-500/5">{{ stats.total_guardias }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL INSTRUCTIVO DE CALENDARIO -->
        <div v-if="modalCalendarioAbierto" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in">
            <div class="w-full max-w-xl bg-zinc-900 border border-indigo-500/30 rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95">

                <div class="p-6 border-b border-zinc-800 flex justify-between items-center bg-zinc-950/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-500/20 text-indigo-400 rounded-xl"><CalendarClock class="w-5 h-5"/></div>
                        <div>
                            <h3 class="font-bold text-white text-lg leading-tight">Sincroniza tus Guardias</h3>
                            <p class="text-xs text-zinc-400">Enlace en vivo directo a tu móvil.</p>
                        </div>
                    </div>
                    <button @click="modalCalendarioAbierto = false" class="p-2 text-zinc-500 hover:text-white rounded-lg hover:bg-zinc-800 transition-colors cursor-pointer"><X class="w-5 h-5"/></button>
                </div>

                <div class="p-6 space-y-6">

                    <div class="p-4 bg-zinc-950 border border-zinc-800 rounded-xl flex items-center justify-between gap-4">
                        <div class="overflow-hidden flex-1">
                            <span class="text-[10px] uppercase font-bold text-indigo-400 block mb-1">Tu Enlace Secreto</span>
                            <div class="font-mono text-xs text-zinc-300 truncate opacity-80">{{ urlCalendario }}</div>
                        </div>
                        <button @click="copiarEnlaceCalendario" class="p-3 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold rounded-xl shrink-0 transition-colors cursor-pointer flex items-center gap-2">
                            <Copy v-if="!linkCopiado" class="w-4 h-4" />
                            <CheckCircle2 v-else class="w-4 h-4" />
                            {{ linkCopiado ? '¡Copiado!' : 'Copiar URL' }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/50">
                            <h4 class="font-bold text-white text-sm flex items-center gap-2 mb-2"><Monitor class="w-4 h-4 text-blue-400"/> Google Calendar</h4>
                            <ol class="list-decimal pl-4 text-xs text-zinc-400 space-y-1.5 marker:text-blue-500">
                                <li>Abre Google Calendar en un <b>ordenador</b>.</li>
                                <li>Abajo a la izquierda, haz clic en el <b>"+"</b> junto a "Otros calendarios".</li>
                                <li>Selecciona <b>"Desde URL"</b>.</li>
                                <li>Pega el enlace que acabas de copiar y dale a Añadir.</li>
                            </ol>
                        </div>

                        <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/50">
                            <h4 class="font-bold text-white text-sm flex items-center gap-2 mb-2"><Smartphone class="w-4 h-4 text-rose-400"/> Apple (iPhone / Mac)</h4>
                            <ol class="list-decimal pl-4 text-xs text-zinc-400 space-y-1.5 marker:text-rose-500">
                                <li>Abre <b>Ajustes</b> > Calendario.</li>
                                <li>Toca <b>Cuentas</b> > Añadir cuenta.</li>
                                <li>Selecciona <b>"Otra"</b>.</li>
                                <li>Toca <b>Añadir calendario suscrito</b> y pega ahí tu enlace.</li>
                            </ol>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-blue-500/10 border border-blue-500/20 text-xs text-blue-200 flex items-start gap-2">
                        <Info class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" />
                        <p>Los calendarios suscritos pueden tardar unas horas en refrescarse en tu móvil cuando se genera un cuadrante nuevo. Es una limitación técnica de Google/Apple, no de CliniCore.</p>
                    </div>

                    <!-- F3-05 / F6-01: SINCRONIZACIÓN PUSH EN COLA Y OAUTH2 CON GOOGLE CALENDAR -->
                    <form @submit.prevent="guardarConfigSyncGcal" class="p-4 rounded-xl bg-zinc-950 border border-indigo-500/30 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-white block">Sincronización Push Asíncrona (Google Calendar API)</span>
                                <span class="text-[11px] text-zinc-400">
                                    Actualiza automáticamente tus turnos en cola al publicar un cuadrante o aprobarse una permuta.
                                </span>
                            </div>
                            <input
                                type="checkbox"
                                v-model="formGcalSync.gcal_sync_enabled"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-indigo-500"
                            />
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-zinc-800/80">
                            <div class="flex items-center gap-2 text-xs">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase border"
                                    :class="gcal_sync?.oauth_connected
                                        ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                                        : 'bg-zinc-900 text-zinc-400 border-zinc-800'"
                                >
                                    {{ gcal_sync?.oauth_connected ? 'OAuth2 Conectado' : 'OAuth2 No Vinculado' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    v-if="!gcal_sync?.oauth_connected"
                                    href="/calendario/oauth/redirect"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-xs transition-colors"
                                >
                                    Vincular con Google OAuth2
                                </a>
                                <button
                                    v-else
                                    type="button"
                                    @click="desconectarOauthGcal"
                                    class="px-3 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/30 font-bold text-xs cursor-pointer transition-colors"
                                >
                                    Desconectar OAuth2
                                </button>
                            </div>
                        </div>

                        <div v-if="formGcalSync.gcal_sync_enabled" class="flex flex-col sm:flex-row gap-2 pt-1">
                            <input
                                v-model="formGcalSync.gcal_calendar_id"
                                type="text"
                                placeholder="ID de Calendario (ej: primary)"
                                class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-white font-mono outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="formGcalSync.processing"
                                class="px-4 py-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold text-xs cursor-pointer"
                            >
                                Guardar y Encolar Sync
                            </button>
                        </div>
                        <div v-else class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="formGcalSync.processing"
                                class="px-3 py-1 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold text-[11px] cursor-pointer"
                            >
                                Actualizar preferencia
                            </button>
                        </div>
                        <p v-if="gcal_sync?.last_synced_at" class="text-[10px] font-mono text-emerald-400">
                            Última sincronización en cola: {{ gcal_sync.last_synced_at }}
                        </p>
                    </form>

                </div>
            </div>
        </div>

    </div>
</template>
