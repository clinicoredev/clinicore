<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Layers, Activity, CalendarOff, Filter, Users, ChevronLeft, ChevronRight } from '@lucide/vue';

const props = defineProps({
    eventos: Array,
    festivos: Array,
    medicos: Array,
    mes_actual: Number,
    anio_actual: Number
});

const filtroGrupo = ref('todos'); // 'adjuntos', 'residentes', 'todos'
const filtroTipo = ref('todos'); // 'todos', 'guardias', 'ausencias'
const filtroMedico = ref('todos');

const rolesResidentes = ['Residente', 'Residente Mayor'];

const medicosFiltrados = computed(() => {
    return props.medicos.filter(m => {
        if (filtroGrupo.value === 'todos') return true;
        if (filtroGrupo.value === 'adjuntos') return !rolesResidentes.includes(m.rol);
        return rolesResidentes.includes(m.rol);
    });
});

const eventosFiltrados = computed(() => {
    return props.eventos.filter(ev => {
        const cumpleTipo = filtroTipo.value === 'todos' || 
                          (filtroTipo.value === 'guardias' && ev.tipo === 'GUARDIA') ||
                          (filtroTipo.value === 'ausencias' && ev.tipo === 'AUSENCIA');
        
        const cumpleMedico = filtroMedico.value === 'todos' || ev.user_id === filtroMedico.value;
        
        const esResidente = rolesResidentes.includes(ev.rol);
        const cumpleGrupo = filtroGrupo.value === 'todos' ||
                            (filtroGrupo.value === 'adjuntos' && !esResidente) ||
                            (filtroGrupo.value === 'residentes' && esResidente);
        
        return cumpleTipo && cumpleMedico && cumpleGrupo;
    });
});

const statsFiltradas = computed(() => {
    return {
        guardias: eventosFiltrados.value.filter(e => e.tipo === 'GUARDIA').length,
        ausencias: eventosFiltrados.value.filter(e => e.tipo === 'AUSENCIA').length
    };
});

const navegarPeriodo = (mes, anio) => {
    router.get('/calendario-completo', { mes, anio }, { preserveState: true, preserveScroll: true });
};

const mesAnterior = () => {
    if (props.mes_actual === 1) navegarPeriodo(12, props.anio_actual - 1);
    else navegarPeriodo(props.mes_actual - 1, props.anio_actual);
};

const mesSiguiente = () => {
    if (props.mes_actual === 12) navegarPeriodo(1, props.anio_actual + 1);
    else navegarPeriodo(props.mes_actual + 1, props.anio_actual);
};

const nombresMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

const rejillaMaestra = computed(() => {
    const diasEnMes = new Date(props.anio_actual, props.mes_actual, 0).getDate();
    let primerDiaSemana = new Date(props.anio_actual, props.mes_actual - 1, 1).getDay();
    let huecosBlanco = primerDiaSemana === 0 ? 6 : primerDiaSemana - 1;

    const matriz = [];

    for (let i = 0; i < huecosBlanco; i++) {
        matriz.push({ vacio: true });
    }

    for (let dia = 1; dia <= diasEnMes; dia++) {
        const mesStr = String(props.mes_actual).padStart(2, '0');
        const diaStr = String(dia).padStart(2, '0');
        const fechaBusqueda = `${props.anio_actual}-${mesStr}-${diaStr}`;

        const eventosDelDia = eventosFiltrados.value.filter(e => e.fecha === fechaBusqueda);
        const festivoObj = props.festivos?.find(f => f.fecha === fechaBusqueda);
        const esFindeNatural = new Date(props.anio_actual, props.mes_actual - 1, dia).getDay() === 0 || new Date(props.anio_actual, props.mes_actual - 1, dia).getDay() === 6;

        matriz.push({
            vacio: false,
            numero: dia,
            fecha: fechaBusqueda,
            esFinde: esFindeNatural || !!festivoObj,
            esFestivo: !!festivoObj,
            nombreFestivo: festivoObj ? festivoObj.descripcion : '',
            eventos: eventosDelDia
        });
    }

    return matriz;
});
</script>

<template>
    <Head title="Control Maestro" />

    <div class="space-y-6">
        
        <!-- Header -->
        <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xl">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><Layers class="w-5 h-5" /></span>
                    <h1 class="text-lg font-bold text-white tracking-tight">Sala de Control Maestro Operativo</h1>
                </div>
                <p class="text-xs text-zinc-400 mt-1">
                    Supervisión unificada de cobertura de guardias (Adjuntos y MIR), rotaciones y ausencias autorizadas.
                </p>
            </div>

            <div class="flex items-center gap-2 bg-zinc-950 p-1.5 rounded-xl border border-zinc-800 shrink-0 self-start lg:self-auto">
                <button @click="mesAnterior" class="p-1.5 text-zinc-400 hover:text-white rounded-lg hover:bg-zinc-800 cursor-pointer">
                    <ChevronLeft class="w-4 h-4" />
                </button>
                <span class="px-3 text-xs font-bold text-emerald-400 font-mono min-w-[110px] text-center">
                    {{ nombresMeses[mes_actual - 1] }} {{ anio_actual }}
                </span>
                <button @click="mesSiguiente" class="p-1.5 text-zinc-400 hover:text-white rounded-lg hover:bg-zinc-800 cursor-pointer">
                    <ChevronRight class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- BARRA DE FILTROS INTERACTIVA -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 flex flex-col lg:flex-row justify-between items-center gap-4 shadow-lg">
            
            <div class="flex flex-col sm:flex-row items-center gap-4 w-full lg:w-auto flex-wrap">
                
                <!-- Selector de Grupos -->
                <div class="flex bg-zinc-950 rounded-xl p-1 border border-zinc-800 w-full sm:w-auto">
                    <button @click="filtroGrupo = 'todos'" :class="filtroGrupo === 'todos' ? 'bg-zinc-800 text-white shadow' : 'text-zinc-500 hover:text-zinc-300'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all cursor-pointer">
                        Todos
                    </button>
                    <button @click="filtroGrupo = 'adjuntos'; filtroMedico = 'todos'" :class="filtroGrupo === 'adjuntos' ? 'bg-zinc-800 text-white shadow' : 'text-zinc-500 hover:text-zinc-300'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <Users class="w-3.5 h-3.5" /> Adjuntos
                    </button>
                    <button @click="filtroGrupo = 'residentes'; filtroMedico = 'todos'" :class="filtroGrupo === 'residentes' ? 'bg-zinc-800 text-white shadow' : 'text-zinc-500 hover:text-zinc-300'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all cursor-pointer">
                        Residentes
                    </button>
                </div>

                <!-- Selector de Tipos -->
                <div class="flex bg-zinc-950 rounded-xl p-1 border border-zinc-800 w-full sm:w-auto">
                    <button @click="filtroTipo = 'todos'" :class="filtroTipo === 'todos' ? 'bg-zinc-800 text-white shadow' : 'text-zinc-500 hover:text-zinc-300'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <Layers class="w-3.5 h-3.5" /> Todo
                    </button>
                    <button @click="filtroTipo = 'guardias'" :class="filtroTipo === 'guardias' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow' : 'text-zinc-500 hover:text-zinc-300 border border-transparent'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <Activity class="w-3.5 h-3.5" /> Guardias
                    </button>
                    <button @click="filtroTipo = 'ausencias'" :class="filtroTipo === 'ausencias' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20 shadow' : 'text-zinc-500 hover:text-zinc-300 border border-transparent'" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <CalendarOff class="w-3.5 h-3.5" /> Ausencias
                    </button>
                </div>

                <!-- Selector de Médicos Dinámico -->
                <div class="relative w-full sm:w-56">
                    <Filter class="w-4 h-4 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2" />
                    <select v-model="filtroMedico" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-300 text-xs rounded-xl pl-9 pr-3 py-2.5 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none cursor-pointer">
                        <option class="bg-zinc-900" value="todos">Cualquier facultativo...</option>
                        <option class="bg-zinc-900" v-for="medico in medicosFiltrados" :key="medico.id" :value="medico.id">
                            {{ medico.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Resumen Estadístico Dinámico -->
            <div class="flex gap-2 text-xs font-mono shrink-0">
                <span class="bg-emerald-500/10 text-emerald-400 px-2.5 py-1.5 rounded-lg border border-emerald-500/20">
                    <b class="text-sm">{{ statsFiltradas.guardias }}</b> Guardias
                </span>
                <span class="bg-rose-500/10 text-rose-400 px-2.5 py-1.5 rounded-lg border border-rose-500/20">
                    <b class="text-sm">{{ statsFiltradas.ausencias }}</b> Ausencias
                </span>
            </div>

        </div>

        <!-- Leyenda -->
        <div class="flex flex-wrap gap-4 text-xs px-2 text-zinc-400 font-medium">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-emerald-500/20 border border-emerald-500"></span> Guardia 17h</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-amber-500/20 border border-amber-500"></span> Guardia 24h (Finde/Festivo)</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-rose-500/20 border border-rose-500"></span> Ausencia (Sin guardias)</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-blue-500/20 border border-blue-500"></span> Rotación (Con guardias)</span>
            <span class="flex items-center gap-1.5"><span class="px-1 py-0.5 rounded text-[9px] bg-purple-500/20 text-purple-400 font-bold">MIR</span> Residente</span>
        </div>

        <!-- Rejilla -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 shadow-2xl overflow-hidden">
            
            <div class="grid grid-cols-7 gap-2 text-center text-[11px] font-bold uppercase tracking-wider text-zinc-500 pb-3 border-b border-zinc-800/80 mb-3">
                <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div>
                <div class="text-amber-500/80">Sáb</div><div class="text-amber-500/80">Dom</div>
            </div>

            <div class="grid grid-cols-7 gap-2">
                <div 
                    v-for="(celda, idx) in rejillaMaestra" 
                    :key="idx"
                    class="min-h-[115px] sm:min-h-[135px] rounded-xl p-2 border transition-all flex flex-col"
                    :class="[
                        celda.vacio ? 'bg-zinc-950/10 border-transparent' : 'bg-zinc-950/40 border-zinc-800/80 hover:border-zinc-700',
                        celda.esFinde && !celda.vacio ? 'bg-gradient-to-b from-zinc-950/40 to-amber-950/15 border-amber-500/20' : ''
                    ]"
                >
                    <div v-if="!celda.vacio" class="flex justify-between items-center mb-1">
                        <span class="text-xs font-mono font-bold" :class="celda.esFinde ? 'text-amber-400' : 'text-zinc-500'">
                            {{ celda.numero }}
                        </span>
                        <span v-if="celda.esFestivo" class="text-[8px] font-bold text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded truncate max-w-[80px]" :title="celda.nombreFestivo">
                            🎉 {{ celda.nombreFestivo }}
                        </span>
                    </div>

                    <div v-if="!celda.vacio" class="space-y-1 flex-1 overflow-y-auto max-h-[105px] pr-0.5 custom-scrollbar">
                        
                        <div 
                            v-for="ev in celda.eventos" 
                            :key="ev.id"
                            class="px-2 py-1 rounded text-[10px] font-semibold border flex items-center justify-between gap-1 shadow-xs"
                            :class="{
                                'bg-emerald-500/10 text-emerald-300 border-emerald-500/30': ev.estilo === 'guardia_diaria',
                                'bg-amber-500/10 text-amber-300 border-amber-500/30 font-bold': ev.estilo === 'guardia_finde',
                                'bg-rose-500/10 text-rose-300 border-rose-500/30 line-through opacity-85': ev.estilo === 'ausencia',
                                'bg-blue-500/10 text-blue-300 border-blue-500/30': ev.estilo === 'rotacion_guardias',
                            }"
                            :title="`${ev.tipo}: ${ev.detalle}`"
                        >
                            <div class="flex items-center gap-1 overflow-hidden">
                                <span class="truncate uppercase tracking-tight">{{ ev.medico }}</span>
                                <span v-if="rolesResidentes.includes(ev.rol)" class="text-[8px] bg-purple-500/20 text-purple-300 px-1 rounded shrink-0 font-bold">MIR</span>
                            </div>
                            <span class="text-[8px] opacity-75 font-mono shrink-0">{{ ev.detalle.slice(0,3) }}</span>
                        </div>

                    </div>

                </div>
            </div>

        </div>

    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #27272a; border-radius: 4px; }
</style>