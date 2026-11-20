<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Copy, PartyPopper, Pencil, ShieldAlert, Sparkles, Star, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';

interface FestivoRow {
    id: number;
    fecha_raw: string;
    fecha_formateada: string;
    dia_semana: string;
    mes: string;
    anio: number;
    descripcion: string;
    es_especial: boolean;
}

const props = defineProps<{
    festivosAgrupados: Record<string, FestivoRow[]>;
    anioActual: number;
    permisos: {
        es_admin: boolean;
    };
}>();

const anioPrecarga = ref(props.anioActual || new Date().getFullYear());
const editandoId = ref<number | null>(null);

const form = useForm({
    fecha: '',
    descripcion: '',
    es_especial: false,
});

const iniciarEdicion = (fest: FestivoRow) => {
    editandoId.value = fest.id;
    form.fecha = fest.fecha_raw;
    form.descripcion = fest.descripcion;
    form.es_especial = fest.es_especial;
};

const cancelarEdicion = () => {
    editandoId.value = null;
    form.reset();
};

const guardarFestivo = () => {
    if (editandoId.value !== null) {
        form.patch(`/festivos/${editandoId.value}`, {
            preserveScroll: true,
            onSuccess: () => cancelarEdicion(),
        });
        return;
    }

    form.post('/festivos', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const importarNacionales = () => {
    if (confirm(`¿Importar automáticamente los festivos nacionales y especiales (Navidad, Año Nuevo, Reyes...) para el año ${anioPrecarga.value}?`)) {
        router.post('/festivos/precargar', { anio: anioPrecarga.value }, { preserveScroll: true });
    }
};

const copiarAnioAnterior = () => {
    const origen = anioPrecarga.value - 1;
    if (confirm(`¿Copiar los festivos registrados en ${origen} hacia ${anioPrecarga.value} (sin duplicar fechas ya existentes)?`)) {
        router.post('/festivos/copiar-anio', { anio_origen: origen, anio_destino: anioPrecarga.value }, { preserveScroll: true });
    }
};

const borrarFestivo = (id: number) => {
    if (confirm('¿Eliminar este festivo? Afectará a los próximos cálculos de IA.')) {
        router.delete(`/festivos/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Festivos del Servicio" />

    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- HEADER -->
        <div class="bg-gradient-to-r from-zinc-900 to-rose-950/40 border border-zinc-800 rounded-2xl p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-mono font-bold">
                    <PartyPopper class="w-3.5 h-3.5" /> CONFIGURACIÓN GLOBAL DEL SERVICIO
                </div>
                <h2 class="text-xl font-bold text-white tracking-tight">Calendario de Festivos y Fechas Especiales (24h)</h2>
                <p class="text-xs sm:text-sm text-zinc-400 max-w-2xl">
                    El motor lleva una contabilidad anual estricta separando los festivos ordinarios de los <b>Festivos Especiales</b> (Navidad, Nochebuena, Año Nuevo, Reyes) para que nadie repita.
                </p>
            </div>

            <div v-if="permisos.es_admin" class="flex flex-wrap items-center gap-2 bg-zinc-950/80 p-2 rounded-xl border border-zinc-800 shrink-0">
                <select v-model="anioPrecarga" class="bg-zinc-900 text-white text-xs font-mono font-bold px-2.5 py-2 rounded-lg border border-zinc-800 outline-none">
                    <option :value="2026">2026</option>
                    <option :value="2027">2027</option>
                    <option :value="2028">2028</option>
                </select>
                <button @click="importarNacionales" class="px-3.5 py-2 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white border border-rose-500/30 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                    <Sparkles class="w-3.5 h-3.5" /> Precargar Nacionales
                </button>
                <button @click="copiarAnioAnterior" class="px-3.5 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-700 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                    <Copy class="w-3.5 h-3.5" /> Copiar {{ anioPrecarga - 1 }}
                </button>
            </div>
        </div>

        <!-- ALERTA DE ERRORES -->
        <div v-if="$page.props.errors.festivos" class="p-4 bg-rose-500/20 border-2 border-rose-500/50 rounded-xl flex items-center gap-3 text-rose-200">
            <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
            <div>
                <span class="font-bold text-rose-400 uppercase text-xs tracking-wider block">Aviso</span>
                <p class="text-sm font-medium mt-0.5">{{ $page.props.errors.festivos }}</p>
            </div>
            <button @click="delete $page.props.errors.festivos" class="ml-auto p-1 hover:bg-rose-500/20 rounded-lg text-rose-400 cursor-pointer">
                <X class="w-5 h-5"/>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- FORMULARIO DE INGRESO -->
            <div v-if="permisos.es_admin" class="lg:col-span-1">
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 sticky top-6">
                    <h3 class="font-bold text-white mb-4 flex items-center justify-between gap-2 text-sm">
                        <span class="flex items-center gap-2">
                            <CalendarDays class="w-4 h-4 text-rose-400" />
                            {{ editandoId !== null ? 'Editar Festivo' : 'Registrar Festivo' }}
                        </span>
                        <button
                            v-if="editandoId !== null"
                            type="button"
                            @click="cancelarEdicion"
                            class="text-[11px] text-zinc-400 hover:text-white cursor-pointer"
                        >
                            Cancelar
                        </button>
                    </h3>
                    
                    <form @submit.prevent="guardarFestivo" class="space-y-4 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Fecha Exacta</label>
                            <input v-model="form.fecha" type="date" required class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 font-mono outline-none focus:border-rose-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Motivo / Festividad</label>
                            <input v-model="form.descripcion" type="text" placeholder="Ej: Reyes, Corpus Christi..." class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 outline-none focus:border-rose-500 transition-colors" />
                        </div>

                        <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all" :class="form.es_especial ? 'bg-amber-500/10 border-amber-500/40 text-amber-300' : 'bg-zinc-950 border-zinc-800 text-zinc-400'">
                            <input type="checkbox" v-model="form.es_especial" class="mt-0.5 w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500">
                            <div>
                                <span class="font-bold text-xs flex items-center gap-1 text-white">
                                    <Star class="w-3.5 h-3.5 text-amber-400 fill-amber-400" /> Festivo Especial
                                </span>
                                <span class="block text-[10px] text-zinc-400 mt-0.5 leading-relaxed">
                                    Márcalo para días críticos (Año Nuevo, Reyes, Nochebuena, Navidad). Se repartirán en una bolsa de equidad anual exclusiva.
                                </span>
                            </div>
                        </label>

                        <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-rose-500 hover:bg-rose-400 text-white font-bold rounded-lg transition-colors cursor-pointer mt-2 disabled:opacity-50 shadow-lg shadow-rose-500/20">
                            {{ editandoId !== null ? 'Guardar Cambios' : 'Añadir al Calendario' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- LISTADO DE FESTIVOS AGRUPADOS POR AÑO -->
            <div :class="permisos.es_admin ? 'lg:col-span-2' : 'lg:col-span-3'" class="space-y-6">
                <div v-if="Object.keys(festivosAgrupados).length === 0" class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl p-12 text-center">
                    <PartyPopper class="w-12 h-12 text-zinc-700 mx-auto mb-3" />
                    <h3 class="text-base font-bold text-zinc-400">Sin festivos registrados</h3>
                    <p class="text-xs text-zinc-500 mt-1">Usa el botón "Precargar Nacionales" arriba a la derecha para empezar rápido.</p>
                </div>

                <div v-for="(festivos, anio) in festivosAgrupados" :key="anio" class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                    <div class="bg-zinc-950/50 px-6 py-3.5 border-b border-zinc-800 flex items-center justify-between">
                        <h3 class="text-base font-black text-white font-mono">
                            Año {{ anio }}
                        </h3>
                        <span class="px-2.5 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold">{{ festivos.length }} festivos</span>
                    </div>
                    
                    <div class="divide-y divide-zinc-800/60 p-2">
                        <div v-for="fest in festivos" :key="fest.id" class="flex justify-between items-center p-3 hover:bg-zinc-800/30 rounded-lg transition-colors group">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 rounded-lg flex flex-col items-center justify-center shrink-0 border" :class="fest.es_especial ? 'bg-amber-500/15 border-amber-500/40' : 'bg-rose-500/10 border-rose-500/20'">
                                    <span class="text-[9px] font-bold uppercase leading-none" :class="fest.es_especial ? 'text-amber-400' : 'text-rose-400'">{{ fest.mes.substring(0,3) }}</span>
                                    <span class="text-base font-black leading-tight mt-0.5 font-mono" :class="fest.es_especial ? 'text-amber-200' : 'text-rose-300'">{{ fest.fecha_formateada.split('/')[0] }}</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-bold text-white">{{ fest.descripcion }}</p>
                                        <span v-if="fest.es_especial" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            ★ Especial
                                        </span>
                                    </div>
                                    <p class="text-xs font-mono text-zinc-500">{{ fest.dia_semana }}, {{ fest.fecha_formateada }}</p>
                                </div>
                            </div>
                            <div v-if="permisos.es_admin" class="flex items-center gap-1">
                                <button @click="iniciarEdicion(fest)" class="text-zinc-500 hover:text-amber-300 hover:bg-amber-500/10 p-2 rounded-lg transition-all cursor-pointer" title="Editar festivo">
                                    <Pencil class="w-4 h-4"/>
                                </button>
                                <button @click="borrarFestivo(fest.id)" class="text-zinc-500 hover:text-rose-400 hover:bg-rose-500/10 p-2 rounded-lg transition-all cursor-pointer" title="Eliminar festivo">
                                    <Trash2 class="w-4 h-4"/>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>