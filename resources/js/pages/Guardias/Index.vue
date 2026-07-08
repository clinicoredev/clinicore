<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    CalendarDays, Cpu, ShieldAlert, Trash2, Ban, 
    Sparkles, User, CheckCircle2, BarChart3, X, ArrowLeftRight, Pin, ChevronLeft, ChevronRight, Settings2
} from '@lucide/vue';

const props = defineProps({
    guardias: Array,
    limitaciones: Array,
    medicos: Array,
    equidad: Array,
    permisos: Object,
    mes_actual: Number,
    anio_actual: Number
});

const pestanaActual = ref('cuadrante');

// =========================================================================
// SISTEMA DE NAVEGACIÓN TEMPORAL (FILTROS)
// =========================================================================
const mesFiltro = ref(props.mes_actual);
const anioFiltro = ref(props.anio_actual);

const aniosDisponibles = computed(() => {
    const actual = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, i) => actual - 1 + i);
});

const navegarMes = () => {
    router.get('/guardias', { mes: mesFiltro.value, anio: anioFiltro.value }, {
        preserveState: true, 
        preserveScroll: true
    });
};

const mesAnterior = () => {
    if (mesFiltro.value === 1) { mesFiltro.value = 12; anioFiltro.value--; }
    else { mesFiltro.value--; }
    navegarMes();
};

const mesSiguiente = () => {
    if (mesFiltro.value === 12) { mesFiltro.value = 1; anioFiltro.value++; }
    else { mesFiltro.value++; }
    navegarMes();
};

// =========================================================================
// MÁQUINA DE ESTADOS: PERMUTA PRO
// =========================================================================
const guardiaOrigen = ref(null);

const iniciarOEjecutarPermuta = (celda) => {
    if (!guardiaOrigen.value) {
        guardiaOrigen.value = celda;
        return;
    }
    if (guardiaOrigen.value.id === celda.id) {
        guardiaOrigen.value = null; 
        return;
    }
    if (confirm(`¿Confirmar intercambio de turnos entre ${guardiaOrigen.value.facultativo} y ${celda.facultativo}?`)) {
        router.post('/guardias/permutar', {
            origen_id: guardiaOrigen.value.id,
            destino_id: celda.id
        }, {
            preserveScroll: true,
            onSuccess: () => { guardiaOrigen.value = null; }
        });
    }
};

const cancelarPermuta = () => { guardiaOrigen.value = null; };

// =========================================================================
// ACCIONES QUIRÚRGICAS Y NUCLEARES
// =========================================================================
const borrarGuardia = (id) => {
    if (confirm('¿Seguro que deseas eliminar este turno específico?')) {
        router.delete(`/guardias/${id}`, { preserveScroll: true });
    }
};

const nuclearVaciarMes = () => {
    if (confirm('⚠️ ALERTA MÁXIMA: Esta acción borrará TODAS las guardias de ESTE MES. ¿Continuar?')) {
        router.delete(`/guardias/vaciar-mes?mes=${mesFiltro.value}&anio=${anioFiltro.value}`, {
            preserveScroll: true
        });
    }
};

// =========================================================================
// FORMULARIOS INERTIA (CON FILTRO DE MÉDICOS)
// =========================================================================
const formGenerador = useForm({
    mes: mesFiltro.value,
    anio: anioFiltro.value,
    usar_plantilla_completa: true,
    medicos_incluidos: props.medicos.map(m => m.id) // Por defecto, todos marcados
});

const dispararAlgoritmo = () => {
    formGenerador.mes = mesFiltro.value;
    formGenerador.anio = anioFiltro.value;
    
    if (formGenerador.medicos_incluidos.length === 0) {
        alert('Debes incluir al menos un médico en el algoritmo.');
        return;
    }

    if (confirm(`Esto calculará el cuadrante de ${mesFiltro.value}/${anioFiltro.value}. ¿Continuar?`)) {
        formGenerador.post('/guardias/generar');
    }
};

const formLimitacion = useForm({
    user_id: props.medicos[0]?.id ?? '',
    tipo: 'dia_semana',
    valor: '1',
    motivo: ''
});

const formManual = useForm({
    user_id: '',
    fecha: '',
    tipo: 'diaria_17h'
});

const guardarRegla = () => { formLimitacion.post('/guardias/limitaciones', { onSuccess: () => formLimitacion.reset('motivo') }); };
const guardarGuardiaManual = () => { formManual.post('/guardias/manual', { preserveScroll: true, onSuccess: () => formManual.reset('user_id', 'fecha') }); };

// =========================================================================
// EL CONJURO DE LA CUADRÍCULA: Generador de Matriz
// =========================================================================
const diasMatriz = computed(() => {
    if (!props.guardias || props.guardias.length === 0) return [];
    const primeraGuardia = props.guardias[0];
    const diaNombreLimpio = primeraGuardia.dia_semana.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    const mapaDias = { 'lunes': 0, 'martes': 1, 'miercoles': 2, 'jueves': 3, 'viernes': 4, 'sabado': 5, 'domingo': 6 };
    const desfaseInicial = mapaDias[diaNombreLimpio] ?? 0;
    
    const matrizCompleta = [];
    for (let i = 0; i < desfaseInicial; i++) { matrizCompleta.push({ esRelleno: true }); }
    
    props.guardias.forEach(g => {
        const numeroDia = parseInt(g.fecha_formateada.split('/')[0]);
        matrizCompleta.push({ esRelleno: false, numero: numeroDia, ...g });
    });
    return matrizCompleta;
});
</script>

<template>
    <Head title="Inteligencia de Turnos" />

    <div class="space-y-4 sm:space-y-6 relative max-w-full overflow-hidden">
        
        <!-- BANNER PERMUTA -->
        <div v-if="guardiaOrigen" class="p-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl sm:rounded-2xl flex flex-col sm:flex-row justify-between sm:items-center gap-3 shadow-lg animate-bounce">
            <div class="flex items-center gap-3 text-xs">
                <ArrowLeftRight class="w-5 h-5 animate-pulse shrink-0" />
                <div>
                    <span class="font-black uppercase tracking-wider block">Modo Permuta Activo</span>
                    Intercambio pendiente de <strong class="underline">{{ guardiaOrigen.facultativo }}</strong>.
                </div>
            </div>
            <button @click="cancelarPermuta" class="p-2 w-full sm:w-auto bg-black/20 hover:bg-black/40 rounded-lg text-center flex justify-center transition-all cursor-pointer">
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- CABECERA PRINCIPAL Y CONFIGURACIÓN IA -->
        <div class="bg-gradient-to-r from-zinc-900 via-zinc-900 to-emerald-950/40 border border-zinc-800 rounded-xl sm:rounded-2xl shadow-xl overflow-hidden">
            
            <div class="p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 sm:gap-6">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] sm:text-xs font-mono font-bold">
                        <Cpu class="w-3.5 h-3.5 animate-spin" />
                        MOTOR CSP v2.4 (HEURÍSTICO VORAZ)
                    </div>
                    <h2 class="text-lg sm:text-xl font-bold text-white tracking-tight">Planificador Sanitario Automatizado</h2>
                </div>

                <div v-if="permisos.es_jefe" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 shrink-0">
                    <button @click="nuclearVaciarMes" class="px-4 py-2.5 rounded-lg border border-rose-500/30 text-rose-400 hover:bg-rose-500/10 transition-all font-bold text-xs sm:text-sm flex justify-center items-center gap-2">
                        <Trash2 class="w-4 h-4" /> Limpiar
                    </button>
                    <button @click="dispararAlgoritmo" :disabled="formGenerador.processing" class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-xs sm:text-sm transition-all shadow-lg flex justify-center items-center gap-2 disabled:opacity-50">
                        <Sparkles class="w-4 h-4 fill-zinc-950" />
                        {{ formGenerador.processing ? 'Resolviendo...' : 'Generar IA' }}
                    </button>
                    <div class="flex items-center gap-2">
                        <a :href="`/guardias/exportar/excel?mes=${mesFiltro}&anio=${anioFiltro}`" class="flex-1 text-center px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 font-bold text-xs border border-emerald-500/30">📊 Excel</a>
                        <a :href="`/guardias/exportar/pdf?mes=${mesFiltro}&anio=${anioFiltro}`" class="flex-1 text-center px-3 py-2 rounded-lg bg-rose-500/10 text-rose-400 font-bold text-xs border border-rose-500/30">📄 PDF</a>
                    </div>
                </div>
            </div>

            <!-- PANEL PLEGABLE: CONFIGURACIÓN DE PLANTILLA PARA EL ALGORITMO -->
            <div v-if="permisos.es_jefe" class="border-t border-zinc-800/80 bg-zinc-950/50 p-4 sm:px-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" v-model="formGenerador.usar_plantilla_completa" class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-zinc-950">
                    <span class="text-sm font-semibold text-zinc-300 flex items-center gap-2">
                        <Settings2 class="w-4 h-4 text-emerald-400" /> Usar a toda la plantilla activa para este mes
                    </span>
                </label>

                <div v-if="!formGenerador.usar_plantilla_completa" class="mt-4 pt-4 border-t border-zinc-800/50 animate-in slide-in-from-top-2">
                    <span class="block text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3">Selecciona quién entra en el algoritmo:</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <label v-for="m in medicos" :key="m.id" class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors" :class="formGenerador.medicos_incluidos.includes(m.id) ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-zinc-900 border-zinc-800 text-zinc-500'">
                            <input type="checkbox" :value="m.id" v-model="formGenerador.medicos_incluidos" class="hidden">
                            <CheckCircle2 class="w-4 h-4" :class="formGenerador.medicos_incluidos.includes(m.id) ? 'opacity-100' : 'opacity-0'" />
                            <span class="text-xs font-bold truncate">{{ m.name }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="formGenerador.errors.algoritmo" class="p-4 sm:p-5 rounded-xl sm:rounded-2xl bg-rose-500/20 border-2 border-rose-500/50 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 text-rose-200">
            <ShieldAlert class="w-6 h-6 sm:w-8 sm:h-8 text-rose-400 shrink-0" />
            <div>
                <span class="font-bold text-rose-400 uppercase text-[10px] sm:text-xs tracking-wider block">Fallo de Satisfacción</span>
                <p class="text-xs sm:text-sm font-mono mt-0.5">{{ formGenerador.errors.algoritmo }}</p>
            </div>
        </div>

        <!-- BARRA MES/AÑO RESPONSIVE -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between bg-zinc-900 border border-zinc-800 p-2 rounded-xl shadow-sm gap-3">
            <div class="flex items-center justify-center sm:justify-start gap-2 px-2 text-zinc-400 font-bold text-[11px] sm:text-sm uppercase tracking-wider">
                <CalendarDays class="w-4 h-4 text-emerald-400" /> Consultando periodo:
            </div>
            
            <div class="flex items-center justify-center gap-1 w-full sm:w-auto">
                <button @click="mesAnterior" class="p-2 sm:p-2.5 bg-zinc-950 text-zinc-400 rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center">
                    <ChevronLeft class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
                <select v-model="mesFiltro" @change="navegarMes" class="bg-zinc-950 text-emerald-400 font-bold text-xs sm:text-sm rounded-lg border-zinc-800 px-2 py-2 sm:px-3 text-center appearance-none">
                    <option :value="1">Ene</option><option :value="2">Feb</option><option :value="3">Mar</option>
                    <option :value="4">Abr</option><option :value="5">May</option><option :value="6">Jun</option>
                    <option :value="7">Jul</option><option :value="8">Ago</option><option :value="9">Sep</option>
                    <option :value="10">Oct</option><option :value="11">Nov</option><option :value="12">Dic</option>
                </select>
                <select v-model="anioFiltro" @change="navegarMes" class="bg-zinc-950 text-emerald-400 font-bold text-xs sm:text-sm rounded-lg border-zinc-800 px-2 py-2 sm:px-3 text-center appearance-none">
                    <option v-for="a in aniosDisponibles" :key="a" :value="a">{{ a }}</option>
                </select>
                <button @click="mesSiguiente" class="p-2 sm:p-2.5 bg-zinc-950 text-zinc-400 rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center">
                    <ChevronRight class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
            </div>
        </div>

        <!-- PESTAÑAS NAVEGABLES (SCROLL LATERAL EN MÓVIL) -->
        <div class="flex overflow-x-auto gap-1 sm:gap-2 border-b border-zinc-800 pb-px hide-scrollbar">
            <button @click="pestanaActual = 'cuadrante'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2" :class="pestanaActual === 'cuadrante' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <BarChart3 class="w-3.5 h-3.5" /> Equidad
            </button>
            <button @click="pestanaActual = 'calendario'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2" :class="pestanaActual === 'calendario' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <CalendarDays class="w-3.5 h-3.5" /> Cuadrícula
            </button>
            <button @click="pestanaActual = 'reglas'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2" :class="pestanaActual === 'reglas' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <Ban class="w-3.5 h-3.5" /> Reglas ({{ limitaciones.length }})
            </button>
            <button @click="pestanaActual = 'manual'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2" :class="pestanaActual === 'manual' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <Pin class="w-3.5 h-3.5" /> Manual
            </button>
        </div>

        <!-- ==================== PESTAÑA: EQUIDAD ==================== -->
        <div v-if="pestanaActual === 'cuadrante'" class="space-y-4 sm:space-y-6 animate-in fade-in duration-200">
            <div v-if="guardias.length > 0" class="p-4 sm:p-5 bg-zinc-900 border border-zinc-800 rounded-xl">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div v-for="eq in equidad" :key="eq.nombre" class="bg-zinc-950 p-2 sm:p-3 rounded-lg border border-zinc-800 font-mono">
                        <div class="text-zinc-300 font-bold truncate text-[10px] sm:text-xs">{{ eq.nombre }}</div>
                        <div class="mt-1 flex justify-between text-[9px] sm:text-[11px]">
                            <span class="text-zinc-500">Total: <b class="text-white">{{ eq.totales }}</b></span>
                            <span class="text-emerald-500">Finde: <b class="text-emerald-400">{{ eq.findes }}</b></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla responsive -->
            <div v-if="guardias.length > 0" class="bg-zinc-900 border border-zinc-800 rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[500px]">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400 text-[10px] sm:text-xs uppercase font-semibold">
                            <th class="py-3 px-4 sm:px-6">Día</th>
                            <th class="py-3 px-4 sm:px-6">Facultativo</th>
                            <th class="py-3 px-4 sm:px-6">Turno</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800 text-xs sm:text-sm">
                        <tr v-for="item in guardias" :key="item.id">
                            <td class="py-2 sm:py-3.5 px-4 sm:px-6 font-mono font-bold text-white whitespace-nowrap">
                                {{ item.fecha_formateada }} <span class="text-[9px] sm:text-xs text-zinc-500 ml-1">({{ item.dia_semana }})</span>
                            </td>
                            <td class="py-2 sm:py-3.5 px-4 sm:px-6 font-semibold flex items-center gap-1 sm:gap-2">
                                {{ item.facultativo }} <Pin v-if="item.is_manual" class="w-3 h-3 text-amber-400" />
                            </td>
                            <td class="py-2 sm:py-3.5 px-4 sm:px-6">
                                <span class="px-1.5 sm:px-2 py-0.5 rounded text-[9px] sm:text-xs font-mono whitespace-nowrap" :class="item.es_finde ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-zinc-800 text-zinc-400'">
                                    {{ item.tipo_badge }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="guardias.length === 0" class="text-center p-8 sm:p-12 bg-zinc-900/50 border border-dashed border-zinc-800 rounded-xl">
                <p class="text-sm text-zinc-500">No hay guardias registradas. Ejecuta la IA.</p>
            </div>
        </div>

        <!-- ==================== PESTAÑA: CALENDARIO (CUADRÍCULA RESPONSIVE) ==================== -->
        <div v-if="pestanaActual === 'calendario'" class="space-y-2 sm:space-y-4 animate-in fade-in duration-200">
            <div class="grid grid-cols-7 gap-1 sm:gap-2 text-center text-[9px] sm:text-xs font-bold uppercase tracking-wider text-zinc-500 bg-zinc-950 p-1.5 sm:p-3 rounded-lg border border-zinc-800/60">
                <div><span class="hidden sm:inline">Lunes</span><span class="sm:hidden">L</span></div>
                <div><span class="hidden sm:inline">Martes</span><span class="sm:hidden">M</span></div>
                <div><span class="hidden sm:inline">Miércoles</span><span class="sm:hidden">X</span></div>
                <div><span class="hidden sm:inline">Jueves</span><span class="sm:hidden">J</span></div>
                <div><span class="hidden sm:inline">Viernes</span><span class="sm:hidden">V</span></div>
                <div class="text-amber-500/80"><span class="hidden sm:inline">Sábado</span><span class="sm:hidden">S</span></div>
                <div class="text-amber-500/80"><span class="hidden sm:inline">Domingo</span><span class="sm:hidden">D</span></div>
            </div>

            <div class="grid grid-cols-7 gap-1 sm:gap-2">
                <div 
                    v-for="(celda, index) in diasMatriz" 
                    :key="index"
                    class="min-h-[85px] sm:min-h-[125px] rounded-md sm:rounded-xl border p-1 sm:p-2 flex flex-col justify-between transition-all group relative overflow-hidden"
                    :class="[celda.esRelleno ? 'bg-zinc-950/20 border-zinc-900/40 opacity-20' : 'bg-zinc-900 border-zinc-800 shadow-sm', celda.es_finde ? 'bg-gradient-to-b from-zinc-900 to-amber-950/10 border-amber-500/10' : '']"
                >
                    <div v-if="!celda.esRelleno" class="flex justify-between items-center z-10 relative">
                        <span class="font-mono text-xs sm:text-sm font-black" :class="celda.es_finde ? 'text-amber-400' : 'text-zinc-500'">{{ celda.numero }}</span>
                        <span class="hidden sm:inline text-[8px] sm:text-[9px] font-mono text-zinc-600 uppercase">{{ celda.es_finde ? '24h' : '17h' }}</span>
                    </div>

                    <div v-if="!celda.esRelleno" class="mt-1 sm:mt-2 flex-1 flex flex-col justify-end sm:justify-center z-10 relative">
                        <div class="w-full p-1 sm:p-2 rounded sm:rounded-lg border text-center flex flex-col items-center justify-center min-h-[35px] sm:min-h-[50px] shadow-sm transition-all" :class="[celda.is_manual ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' : (celda.es_finde ? 'bg-zinc-950/50 text-amber-200 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20')]">
                            <div class="flex items-center gap-0.5 sm:gap-1 justify-center w-full">
                                <span class="block truncate text-[8px] sm:text-[11px] font-black uppercase">{{ celda.facultativo.replace('Dr. ', '').replace('Dra. ', '') }}</span>
                                <Pin v-if="celda.is_manual" class="hidden sm:block w-3 h-3 text-amber-400 shrink-0" />
                            </div>
                            
                            <div v-if="permisos.es_jefe" class="sm:grid grid-cols-2 gap-1 pt-1.5 mt-1 border-t border-zinc-800/40 opacity-0 group-hover:opacity-100 hidden sm:w-full">
                                <button @click="iniciarOEjecutarPermuta(celda)" type="button" class="p-1 text-zinc-400 hover:text-blue-400 rounded hover:bg-zinc-800 flex items-center justify-center"><ArrowLeftRight class="w-3 h-3" /></button>
                                <button @click="borrarGuardia(celda.id)" type="button" class="p-1 text-zinc-400 hover:text-rose-400 rounded hover:bg-zinc-800 flex items-center justify-center"><Trash2 class="w-3 h-3" /></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PESTAÑA: REGLAS (COLAPSADA EN MÓVIL) ==================== -->
        <div v-if="pestanaActual === 'reglas'" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 animate-in fade-in duration-200">
            <div class="p-4 sm:p-6 bg-zinc-900 border border-zinc-800 rounded-xl h-fit">
                <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2 mb-4"><Ban class="w-4 h-4 text-rose-400" /> Añadir veto</h3>
                <form @submit.prevent="guardarRegla" class="space-y-4 text-xs">
                    <select v-model="formLimitacion.user_id" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5"><option v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                    <select v-model="formLimitacion.tipo" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5"><option value="dia_semana">Día semana</option><option value="fecha_concreta">Día exacto</option></select>
                    <div v-if="formLimitacion.tipo === 'dia_semana'"><select v-model="formLimitacion.valor" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5"><option value="1">Lunes</option><option value="2">Martes</option><option value="3">Miércoles</option><option value="4">Jueves</option><option value="5">Viernes</option><option value="6">Sábado</option><option value="7">Domingo</option></select></div>
                    <div v-else><input v-model="formLimitacion.valor" type="date" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5" /></div>
                    <input v-model="formLimitacion.motivo" type="text" placeholder="Ej: Conciliación" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5" />
                    <button type="submit" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold rounded-lg transition-colors">Registrar</button>
                </form>
            </div>
            <div class="lg:col-span-2 bg-zinc-900 border border-zinc-800 rounded-xl p-4 sm:p-6">
                <h3 class="font-bold text-white mb-4 text-sm sm:text-base flex items-center gap-2"><User class="w-4 h-4 text-emerald-400" /> Activas</h3>
                <div v-if="limitaciones.length === 0" class="text-xs text-zinc-500 italic p-4 text-center border border-dashed border-zinc-800 rounded-lg">Sin restricciones.</div>
                <div v-else class="space-y-3">
                    <div v-for="regla in limitaciones" :key="regla.id" class="p-3 bg-zinc-950 rounded-lg border border-zinc-800 flex flex-col sm:flex-row justify-between sm:items-center text-xs sm:text-sm gap-2">
                        <div><span class="text-emerald-400 font-bold">{{ regla.medico }}</span> <span class="text-zinc-400 ml-1">prohibido: <u class="text-zinc-200 font-mono bg-zinc-900 px-1 py-0.5 rounded">{{ regla.regla }}</u></span></div>
                        <button v-if="permisos.es_jefe" @click="router.delete(`/guardias/limitaciones/${regla.id}`)" class="text-rose-400 bg-rose-500/10 p-1.5 rounded self-end sm:self-auto"><Trash2 class="w-3.5 h-3.5" /></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PESTAÑA: MANUAL ==================== -->
        <div v-if="pestanaActual === 'manual'" class="max-w-md w-full bg-zinc-900 p-4 sm:p-6 rounded-xl border border-zinc-800 mx-auto animate-in fade-in">
            <form @submit.prevent="guardarGuardiaManual" class="space-y-4">
                <select v-model="formManual.user_id" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm"><option value="" disabled selected>Médico...</option><option v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                <input v-model="formManual.fecha" required type="date" class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 font-mono text-xs sm:text-sm" />
                <select v-model="formManual.tipo" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm"><option value="diaria_17h">Diario (17h)</option><option value="festivo_24h">Festivo (24h)</option></select>
                <button type="submit" class="w-full py-3 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black rounded-xl text-xs flex justify-center gap-2"><Pin class="w-4 h-4" /> Fijar Turno</button>
            </form>
        </div>

    </div>
</template>

<style>
.animate-in { animation: fadeIn 0.2s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: translateY(0); } }
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>