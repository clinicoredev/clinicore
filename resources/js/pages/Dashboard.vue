<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { 
    Activity, Users, CalendarDays, Clock, 
    Stethoscope, CheckCircle2, XCircle, AlertCircle, 
    ArrowRight, ShieldCheck, Zap, CalendarClock, UserCheck 
} from '@lucide/vue';
import VueApexCharts from "vue3-apexcharts";

const props = defineProps({
    kpis: Object,
    guardia_hoy: Object,
    mi_proxima_guardia: Object,
    cola_firmas: Array,
    token_calendario: String,
    permisos: Object,
    grafica_fatiga: Object,
});

// Configuración de la gráfica
const chartSeries = [
    { name: 'Mi Carga (Puntos)', data: props.grafica_fatiga.mis_puntos },
    { name: 'Media Departamento', data: props.grafica_fatiga.media_puntos }
];

const chartOptions = {
    chart: { type: 'area', height: 300, toolbar: { show: false }, background: 'transparent', zoom: { enabled: false } },
    colors: ['#10b981', '#6366f1'], // Verde Emerald (Mío) y Azul Indigo (Media)
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    xaxis: { 
        categories: props.grafica_fatiga.categorias,
        labels: { style: { colors: '#71717a' } }, // Zinc 500
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: { labels: { style: { colors: '#71717a' } } },
    grid: { borderColor: '#27272a', strokeDashArray: 4, yaxis: { lines: { show: true } } },
    theme: { mode: 'dark' },
    legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#a1a1aa' } },
    tooltip: { theme: 'dark', y: { formatter: (val) => val + " ptos" } }
};

const page = usePage();
const nombreUsuario = page.props.auth.user.name;

const firmarDesdePortada = (id, nuevoEstado) => {
    router.patch(`/ausencias/${id}/resolver`, { estado: nuevoEstado }, { preserveScroll: true });
};
// Función robusta para copiar enlaces en cualquier entorno (HTTP y HTTPS)
const copiarEnlaceCalendario = () => {
    const url = `${window.location.origin}/feed/calendario/${props.token_calendario}.ics`;

    // Si estamos en HTTPS y la API moderna está disponible
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(() => {
            alert('¡Enlace secreto copiado! Pégalo en Añadir Suscripción en tu app de calendario.');
        });
    } else {
        // Plan B: Fallback "clásico" para entornos locales HTTP (clinicore.test)
        const textArea = document.createElement("textarea");
        textArea.value = url;
        
        // Lo escondemos visualmente
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('¡Enlace secreto copiado! Pégalo en Añadir Suscripción en tu app de calendario.');
        } catch (err) {
            alert('No se pudo copiar el enlace automáticamente. Cópialo de forma manual: ' + url);
        }
        
        document.body.removeChild(textArea);
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
            </div>
        </div>

        <!-- KPIs DINÁMICOS (Cambian según el rol) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- TARJETA 1 -->
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

            <!-- TARJETA 2 -->
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

            <!-- TARJETA 3 -->
            <div class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden group transition-all" :class="kpis.ausencias_pendientes > 0 ? 'border-amber-500/30' : ''">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider" :class="kpis.ausencias_pendientes > 0 ? 'text-amber-400' : 'text-zinc-400'">
                        {{ permisos.es_jefe ? 'Firmas Pendientes' : 'Mis Peticiones' }}
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

            <!-- TARJETA 4 (Solo para Facultativos: Su próxima guardia) -->
            <div v-if="!permisos.es_jefe && mi_proxima_guardia" class="p-5 bg-linear-to-br from-indigo-950 to-zinc-900 border border-indigo-500/30 rounded-2xl relative overflow-hidden transition-all shadow-lg shadow-indigo-500/10">
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

            <!-- Si es Jefe, pintamos un KPI extra genérico para cuadrar la estructura -->
            <div v-if="permisos.es_jefe" class="p-5 bg-zinc-900/90 border border-zinc-800 rounded-2xl relative overflow-hidden transition-all">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Incidencias Hoy</span>
                    <div class="p-2 rounded-xl bg-zinc-800 text-zinc-500"><AlertCircle class="w-4 h-4" /></div>
                </div>
                <div class="mt-4 text-3xl font-black text-zinc-600 font-mono">0</div>
            </div>

            <!-- Widget Suscripción Calendario -->
            <div class="mt-4 p-4 rounded-xl border border-indigo-500/30 bg-indigo-500/10 flex items-center justify-between gap-4">
                <div>
                    <h4 class="text-xs font-bold text-indigo-300">Sincronización Automática</h4>
                    <p class="text-[10px] text-indigo-400/70 mt-0.5">Lleva tus turnos en tu móvil personal</p>
                </div>
                <button 
                    @click="copiarEnlaceCalendario"
                    class="px-3 py-1.5 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold text-[10px] uppercase rounded-lg transition-colors cursor-pointer shrink-0"
                >
                    Copiar Enlace
                </button>
            </div>

        </div>

        <!-- ÁREA PRINCIPAL -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- PANEL CENTRAL -->
            <div class="lg:col-span-2 bg-linear-to-br from-zinc-900 to-zinc-950 border border-zinc-800 rounded-3xl p-6 shadow-2xl flex flex-col justify-between">
                
                <div class="flex items-center justify-between border-b border-zinc-800/80 pb-4">
                    <div class="flex items-center gap-2">
                        <Stethoscope class="w-5 h-5 text-emerald-400" />
                        <h2 class="font-bold text-white tracking-wide">Equipo de Guardia — Hoy</h2>
                    </div>
                    <span class="text-xs font-mono text-zinc-500">{{ new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }) }}</span>
                </div>

                <div v-if="guardia_hoy" class="my-6 p-6 rounded-2xl bg-zinc-900/60 border border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative overflow-hidden">
                    <div class="absolute -right-4 -bottom-4 opacity-5">
                        <Stethoscope class="w-32 h-32" />
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="w-14 h-14 rounded-full bg-emerald-500/10 border-2 border-emerald-500 flex items-center justify-center text-xl font-black text-emerald-400 shrink-0 shadow-lg shadow-emerald-500/10">
                            {{ guardia_hoy.medico.slice(0,2) }}
                        </div>
                        <div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-500 text-zinc-950 font-black uppercase tracking-wider">
                                {{ guardia_hoy.tipo }}
                            </span>
                            <h3 class="text-xl font-bold text-white mt-1.5">{{ guardia_hoy.medico }}</h3>
                            <p class="text-xs text-zinc-400 font-mono mt-0.5">{{ guardia_hoy.email }}</p>
                        </div>
                    </div>

                    <div class="sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-zinc-800 relative z-10">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase block">Parte del servicio:</span>
                        <span class="text-xs text-zinc-300 italic">"{{ guardia_hoy.observaciones }}"</span>
                    </div>
                </div>

                <div v-else class="my-12 text-center py-8 border border-dashed border-zinc-800 rounded-2xl">
                    <CheckCircle2 class="w-8 h-8 text-zinc-600 mx-auto mb-2" />
                    <p class="text-sm font-bold text-zinc-400">Servicio cubierto por plantilla ordinaria</p>
                    <p class="text-xs text-zinc-600 mt-1">No hay turnos de guardia 17h/24h registrados para hoy.</p>
                </div>

                <!-- BOTONES DE ACCIÓN RÁPIDA (Diferenciados por rol) -->
                <div class="grid grid-cols-2 gap-3 pt-4 border-t border-zinc-800/80">
                    <button @click="router.get('/calendario-completo')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Ver Calendario General
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-emerald-400 transition-all" />
                    </button>

                    <!-- Botón exclusivo Jefes -->
                    <button v-if="permisos.es_jefe" @click="router.get('/guardias')" class="p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-between text-xs text-zinc-300 font-medium transition-all cursor-pointer group">
                        Gestionar Cuadrante IA
                        <ArrowRight class="w-4 h-4 text-zinc-500 group-hover:translate-x-1 group-hover:text-emerald-400 transition-all" />
                    </button>
                    
                    <!-- Botón exclusivo Facultativos -->
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
                            {{ permisos.es_jefe ? 'Despacho — Por Firmar' : 'Mis Trámites Recientes' }}
                        </h2>
                        <span v-if="permisos.es_jefe && kpis.ausencias_pendientes > 0" class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    </div>

                    <div v-if="cola_firmas.length === 0" class="text-center py-12">
                        <CheckCircle2 class="w-10 h-10 text-emerald-500/40 mx-auto mb-2" />
                        <p class="text-xs font-bold text-zinc-400">Bandeja limpia</p>
                        <p class="text-[11px] text-zinc-600 mt-0.5">No hay documentos en curso.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div 
                            v-for="item in cola_firmas" 
                            :key="item.id"
                            class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800/80 flex flex-col gap-2.5 relative overflow-hidden"
                        >
                            <!-- Línea de color según estado para facultativos -->
                            <div v-if="!permisos.es_jefe && item.estado === 'aprobada'" class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500"></div>
                            <div v-if="!permisos.es_jefe && item.estado === 'denegada'" class="absolute left-0 top-0 bottom-0 w-1 bg-rose-500"></div>
                            <div v-if="!permisos.es_jefe && item.estado === 'pendiente'" class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500"></div>

                            <div class="flex justify-between items-start">
                                <div :class="!permisos.es_jefe ? 'pl-2' : ''">
                                    <span v-if="permisos.es_jefe" class="text-xs font-bold text-emerald-400">{{ item.medico }}</span>
                                    <span class="block text-[11px] text-zinc-300">{{ item.tipo }}</span>
                                    <span class="text-[10px] font-mono text-zinc-500">{{ item.fechas }}</span>
                                </div>

                                <!-- Botones si eres jefe -->
                                <div v-if="permisos.es_jefe" class="flex gap-1.5 shrink-0">
                                    <button @click="firmarDesdePortada(item.id, 'aprobada')" title="Autorizar" class="p-1.5 bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-zinc-950 rounded-lg border border-emerald-500/30 transition-all cursor-pointer">
                                        <CheckCircle2 class="w-4 h-4" />
                                    </button>
                                    <button @click="firmarDesdePortada(item.id, 'denegada')" title="Denegar" class="p-1.5 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white rounded-lg border border-rose-500/30 transition-all cursor-pointer">
                                        <XCircle class="w-4 h-4" />
                                    </button>
                                </div>
                                
                                <!-- Etiquetas de estado si eres facultativo -->
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
                    <button @click="router.get('/ausencias')" class="text-xs text-zinc-500 hover:text-white transition-colors">
                        Ver registro completo &rarr;
                    </button>
                </div>
            </div>

            <!-- AÑADE ESTO JUSTO DESPUÉS DEL BLOQUE DE TUS TARJETAS KPI -->
        <div class="mt-8 p-6 bg-zinc-900 border border-zinc-800 rounded-2xl shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <Activity class="w-5 h-5 text-emerald-400" />
                        Mapa de Fatiga Acumulada
                    </h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Comparativa YTD de tus puntos de esfuerzo (Diaria=1, Finde=2) contra la media del servicio.</p>
                </div>
            </div>
            
            <div class="w-full h-[300px]">
                <VueApexCharts 
                    type="area" 
                    height="300" 
                    :options="chartOptions" 
                    :series="chartSeries" 
                />
            </div>
        </div>

        </div>

    </div>
</template>