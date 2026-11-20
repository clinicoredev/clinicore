<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    ShieldAlert, PlusCircle, Pencil, Trash2, Clock, 
    Search, Filter, ChevronDown, ChevronUp, ArrowRight, User, Sparkles, Layers
} from '@lucide/vue';

const props = defineProps({
    logs: Object,
    observabilidad: Object,
});

const busqueda = ref('');
const filtroEvento = ref('todos'); // 'todos', 'created', 'updated', 'deleted', 'lotes', 'sync'
const detallesAbiertos = ref({});

const toggleDetalle = (id) => {
    detallesAbiertos.value[id] = !detallesAbiertos.value[id];
};

const logsFiltrados = computed(() => {
    return (props.logs?.data || []).filter(log => {
        const cumpleEvento = filtroEvento.value === 'todos'
            || (filtroEvento.value === 'lotes' && (log.evento === 'batch_generated' || log.evento === 'batch_cleared'))
            || (filtroEvento.value === 'sync' && ['gcal_synced', 'gcal_sync_failed', 'gcal_sync_warning'].includes(log.evento))
            || log.evento === filtroEvento.value;
        const texto = `${log.causante} ${log.modelo} ${log.descripcion} ${log.fecha}`.toLowerCase();
        const cumpleBusqueda = !busqueda.value || texto.includes(busqueda.value.toLowerCase());
        return cumpleEvento && cumpleBusqueda;
    });
});

const obtenerMetaEvento = (evento) => {
    switch(evento) {
        case 'created': 
            return { 
                icon: PlusCircle, 
                label: 'Registro Creado',
                badge: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                color: 'text-emerald-400 bg-zinc-950 border-emerald-500/40' 
            };
        case 'updated': 
            return { 
                icon: Pencil, 
                label: 'Modificación',
                badge: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                color: 'text-amber-400 bg-zinc-950 border-amber-500/40' 
            };
        case 'deleted': 
            return { 
                icon: Trash2, 
                label: 'Eliminación',
                badge: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                color: 'text-rose-400 bg-zinc-950 border-rose-500/40' 
            };
        case 'batch_generated':
            return {
                icon: Sparkles,
                label: 'Generación IA (Lote)',
                badge: 'bg-teal-500/10 text-teal-300 border-teal-500/30',
                color: 'text-teal-300 bg-zinc-950 border-teal-500/40'
            };
        case 'batch_cleared':
            return {
                icon: Layers,
                label: 'Vaciado Mensual (Lote)',
                badge: 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                color: 'text-rose-300 bg-zinc-950 border-rose-500/40'
            };
        case 'gcal_synced':
            return {
                icon: Clock,
                label: 'Sync Calendario OK',
                badge: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30',
                color: 'text-emerald-300 bg-zinc-950 border-emerald-500/40'
            };
        case 'gcal_sync_failed':
        case 'gcal_sync_warning':
            return {
                icon: ShieldAlert,
                label: 'Alerta / Fallo Sync',
                badge: 'bg-rose-500/15 text-rose-300 border-rose-500/30',
                color: 'text-rose-300 bg-zinc-950 border-rose-500/40'
            };
        default: 
            return { 
                icon: ShieldAlert, 
                label: 'Evento del Sistema',
                badge: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                color: 'text-indigo-400 bg-zinc-950 border-indigo-500/40' 
            };
    }
};

// Extrae pares clave-valor legibles de las propiedades de Spatie Activitylog (attributes / old)
const tieneCambios = (propiedades) => {
    if (!propiedades) return false;
    return Object.keys(propiedades.attributes || propiedades.old || propiedades).length > 0;
};
</script>

<template>
    <Head title="Registro de Auditoría" />

    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- CABECERA -->
        <div class="p-6 bg-gradient-to-r from-zinc-900 via-zinc-900 to-indigo-950/30 border border-zinc-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-[10px] font-mono font-bold mb-2">
                    <ShieldAlert class="w-3.5 h-3.5" /> TRAZABILIDAD INMUTABLE RGPD
                </div>
                <h2 class="text-xl font-bold text-white tracking-tight">
                    Registro de Auditoría Clínica
                </h2>
                <p class="text-xs sm:text-sm text-zinc-400 mt-1">
                    Supervisión cronológica de altas, permutas, aprobaciones y cambios en los cuadrantes del servicio.
                </p>
            </div>

            <div class="text-right font-mono text-xs text-zinc-500 shrink-0">
                Mostrando <b class="text-white">{{ logsFiltrados.length }}</b> eventos en esta página
            </div>
        </div>

        <!-- PANEL DE OBSERVABILIDAD DE COLAS Y SINCRONIZACIÓN (F6-07) -->
        <div v-if="observabilidad" class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="p-3.5 rounded-xl bg-zinc-900 border border-zinc-800">
                <span class="block text-[10px] font-bold uppercase text-zinc-500">Usuarios Sync Activos</span>
                <span class="block text-xl font-black font-mono text-white mt-1">{{ observabilidad.usuarios_sync_activos }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-zinc-900 border border-zinc-800">
                <span class="block text-[10px] font-bold uppercase text-zinc-500">Syncs Exitosos</span>
                <span class="block text-xl font-black font-mono text-emerald-400 mt-1">{{ observabilidad.syncs_exitosos }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-zinc-900 border" :class="observabilidad.syncs_fallidos > 0 ? 'border-rose-500/40' : 'border-zinc-800'">
                <span class="block text-[10px] font-bold uppercase text-zinc-500">Fallos / Revocados Sync</span>
                <span class="block text-xl font-black font-mono mt-1" :class="observabilidad.syncs_fallidos > 0 ? 'text-rose-400' : 'text-zinc-300'">
                    {{ observabilidad.syncs_fallidos }}
                </span>
            </div>
            <div class="p-3.5 rounded-xl bg-zinc-900 border border-zinc-800">
                <span class="block text-[10px] font-bold uppercase text-zinc-500">Jobs en Cola</span>
                <span class="block text-xl font-black font-mono text-indigo-300 mt-1">{{ observabilidad.jobs_en_cola }}</span>
            </div>
            <div class="p-3.5 rounded-xl bg-zinc-900 border" :class="observabilidad.jobs_fallidos > 0 ? 'border-rose-500/40' : 'border-zinc-800'">
                <span class="block text-[10px] font-bold uppercase text-zinc-500">Failed Jobs</span>
                <span class="block text-xl font-black font-mono mt-1" :class="observabilidad.jobs_fallidos > 0 ? 'text-rose-400' : 'text-zinc-300'">
                    {{ observabilidad.jobs_fallidos }}
                </span>
            </div>
        </div>

        <!-- BARRA DE BÚSQUEDA Y FILTROS -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="relative w-full sm:w-80">
                <Search class="w-4 h-4 text-zinc-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input 
                    v-model="busqueda" 
                    type="text" 
                    placeholder="Buscar por médico, fecha o módulo..." 
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-xl pl-10 pr-4 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 transition-colors"
                />
            </div>

            <div class="flex items-center gap-1.5 bg-zinc-950 p-1 rounded-xl border border-zinc-800 w-full sm:w-auto overflow-x-auto">
                <button @click="filtroEvento = 'todos'" :class="filtroEvento === 'todos' ? 'bg-zinc-800 text-white shadow' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Todos
                </button>
                <button @click="filtroEvento = 'created'" :class="filtroEvento === 'created' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Altas
                </button>
                <button @click="filtroEvento = 'updated'" :class="filtroEvento === 'updated' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Cambios
                </button>
                <button @click="filtroEvento = 'deleted'" :class="filtroEvento === 'deleted' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Bajas
                </button>
                <button @click="filtroEvento = 'lotes'" :class="filtroEvento === 'lotes' ? 'bg-teal-500/10 text-teal-300 border border-teal-500/20' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Lotes IA
                </button>
                <button @click="filtroEvento = 'sync'" :class="filtroEvento === 'sync' ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'text-zinc-500 hover:text-zinc-300'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    Sync GCal
                </button>
            </div>
        </div>

        <!-- CONTENEDOR TIMELINE -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-xl">
            
            <div v-if="logsFiltrados.length === 0" class="text-center py-14 text-zinc-500">
                <ShieldAlert class="w-10 h-10 mx-auto mb-3 opacity-30" />
                <p class="text-sm font-semibold text-zinc-400">No se encontraron eventos de auditoría</p>
                <p class="text-xs text-zinc-600 mt-1">Prueba a cambiar los filtros de búsqueda.</p>
            </div>

            <!-- TIMELINE VERTICAL -->
            <div v-else class="relative border-l-2 border-zinc-800 ml-4 space-y-6">
                <div v-for="log in logsFiltrados" :key="log.id" class="relative pl-8">
                    
                    <!-- Icono flotante del evento -->
                    <div class="absolute -left-[18px] top-1 p-2 rounded-full border-2 shadow-md" :class="obtenerMetaEvento(log.evento).color">
                        <component :is="obtenerMetaEvento(log.evento).icon" class="w-3.5 h-3.5" />
                    </div>

                    <div class="bg-zinc-950/90 border border-zinc-800/80 hover:border-zinc-700/80 transition-colors rounded-xl p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border" :class="obtenerMetaEvento(log.evento).badge">
                                    {{ obtenerMetaEvento(log.evento).label }}
                                </span>
                                <span class="font-bold text-white flex items-center gap-1">
                                    <User class="w-3.5 h-3.5 text-zinc-500" />
                                    {{ log.causante }}
                                </span>
                                <span class="text-zinc-500 text-xs">en módulo</span> 
                                <span class="px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-mono text-xs font-bold">
                                    {{ log.modelo }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-xs text-zinc-500 font-mono shrink-0">
                                <Clock class="w-3.5 h-3.5" />
                                {{ log.fecha }} <span class="hidden sm:inline text-zinc-600">({{ log.hace_tiempo }})</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 mt-2">
                            <p class="text-xs sm:text-sm text-zinc-300">
                                {{ log.descripcion }}
                            </p>

                            <button 
                                v-if="tieneCambios(log.propiedades)" 
                                @click="toggleDetalle(log.id)"
                                class="text-[11px] font-mono text-indigo-400 hover:text-indigo-300 flex items-center gap-1 shrink-0 cursor-pointer bg-indigo-500/10 px-2.5 py-1 rounded-lg border border-indigo-500/20"
                            >
                                {{ detallesAbiertos[log.id] ? 'Ocultar metadatos' : 'Ver metadatos' }}
                                <ChevronUp v-if="detallesAbiertos[log.id]" class="w-3.5 h-3.5" />
                                <ChevronDown v-else class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- COMPARADOR DE CAMBIOS COLAPSABLE -->
                        <div v-if="detallesAbiertos[log.id] && tieneCambios(log.propiedades)" class="mt-4 pt-3 border-t border-zinc-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs font-mono">
                            
                            <!-- Valores Anteriores (Si existen) -->
                            <div v-if="log.propiedades.old" class="p-3 rounded-lg bg-rose-950/20 border border-rose-500/20">
                                <span class="text-[10px] font-bold uppercase text-rose-400 block mb-2">Valor Anterior</span>
                                <div v-for="(val, key) in log.propiedades.old" :key="key" class="flex justify-between py-0.5 border-b border-rose-500/10 last:border-0">
                                    <span class="text-zinc-500">{{ key }}:</span>
                                    <span class="text-rose-200 truncate max-w-[180px]">{{ val ?? 'null' }}</span>
                                </div>
                            </div>

                            <!-- Valores Nuevos -->
                            <div v-if="log.propiedades.attributes" class="p-3 rounded-lg bg-emerald-950/20 border border-emerald-500/20" :class="!log.propiedades.old ? 'md:col-span-2' : ''">
                                <span class="text-[10px] font-bold uppercase text-emerald-400 block mb-2">Valor Registrado</span>
                                <div v-for="(val, key) in log.propiedades.attributes" :key="key" class="flex justify-between py-0.5 border-b border-emerald-500/10 last:border-0">
                                    <span class="text-zinc-500">{{ key }}:</span>
                                    <span class="text-emerald-200 truncate max-w-[220px]">{{ val ?? 'null' }}</span>
                                </div>
                            </div>

                            <!-- Fallback por si son propiedades personalizadas sin 'attributes'/'old' -->
                            <div v-if="!log.propiedades.attributes && !log.propiedades.old" class="md:col-span-2 p-3 bg-zinc-900 rounded-lg border border-zinc-800 overflow-x-auto">
                                <pre class="text-[10px] text-zinc-400 whitespace-pre-wrap">{{ JSON.stringify(log.propiedades, null, 2) }}</pre>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <!-- PAGINACIÓN SPA CON INERTIA LINK -->
            <div class="mt-8 flex items-center justify-between border-t border-zinc-800 pt-4" v-if="logs.next_page_url || logs.prev_page_url">
                <Link 
                    :href="logs.prev_page_url || '#'" 
                    preserve-scroll
                    :class="{'pointer-events-none opacity-40': !logs.prev_page_url}" 
                    class="px-4 py-2 rounded-lg bg-zinc-950 border border-zinc-800 text-xs font-bold text-zinc-300 hover:text-white hover:border-zinc-700 transition-colors"
                >
                    ← Página Anterior
                </Link>
                <span class="text-xs font-mono text-zinc-500">
                    Página {{ logs.current_page }} de {{ logs.last_page }}
                </span>
                <Link 
                    :href="logs.next_page_url || '#'" 
                    preserve-scroll
                    :class="{'pointer-events-none opacity-40': !logs.next_page_url}" 
                    class="px-4 py-2 rounded-lg bg-zinc-950 border border-zinc-800 text-xs font-bold text-zinc-300 hover:text-white hover:border-zinc-700 transition-colors"
                >
                    Página Siguiente →
                </Link>
            </div>

        </div>
    </div>
</template>