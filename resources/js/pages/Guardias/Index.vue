<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { 
    CalendarDays, Cpu, ShieldAlert, Trash2, Ban, 
    Sparkles, User, CheckCircle2, BarChart3, X, ArrowLeftRight, Pin, ChevronLeft, ChevronRight, Settings2, Sliders, Moon
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

const page = usePage();
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
// SEPARACIÓN Y FILTRADO DE REGLAS
// =========================================================================
const limitacionesRecurrentes = computed(() => {
    return props.limitaciones.filter(l => l.tipo_raw === 'dia_semana');
});

const limitacionesMensuales = computed(() => {
    return props.limitaciones.filter(l => {
        if (l.tipo_raw === 'dia_semana') return false; 
        return true; 
    });
});

const totalReglasVisibles = computed(() => limitacionesRecurrentes.value.length + limitacionesMensuales.value.length);

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
// ACCIONES QUIRÚRGICAS Y RESTRICCIONES
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
// FORMULARIO DE GENERACIÓN CON FILTROS AVANZADOS
// =========================================================================
const formGenerador = useForm({
    mes: mesFiltro.value,
    anio: anioFiltro.value,
    personas_por_dia: 1, 
    usar_plantilla_completa: true,
    medicos_incluidos: props.medicos.map(m => m.id),
    respetar_salientes: true,
    distancia_minima_dias: 2, 
    max_guardias_mes: 0,      
    max_findes_mes: 0,  
    max_diarias_mes: 0,
    prorratear_ausencias: true,      
    usar_memoria_anual: true  
});

const dispararAlgoritmo = () => {
    formGenerador.mes = mesFiltro.value;
    formGenerador.anio = anioFiltro.value;
    
    if (formGenerador.medicos_incluidos.length === 0) {
        alert('Debes incluir al menos un facultativo en el algoritmo.');
        return;
    }

    if (confirm(`Esto calculará el cuadrante de ${mesFiltro.value}/${anioFiltro.value} aplicando las restricciones seleccionadas. ¿Continuar?`)) {
        formGenerador.post('/guardias/generar');
    }
};

const formLimitacion = useForm({
    user_id: props.medicos[0]?.id ?? '',
    tipo: 'dia_semana',
    valor: '1',
    fecha_inicio: '',
    fecha_fin: '',
    motivo: ''
});

const formManual = useForm({
    user_id: '',
    fecha: '',
    tipo: 'diaria_17h'
});

const guardarRegla = () => { 
    formLimitacion.post('/guardias/limitaciones', { 
        onSuccess: () => formLimitacion.reset('motivo', 'fecha_inicio', 'fecha_fin') 
    }); 
};

const guardarGuardiaManual = () => { 
    formManual.post('/guardias/manual', { 
        preserveScroll: true, 
        onSuccess: () => formManual.reset('user_id', 'fecha') 
    }); 
};

// =========================================================================
// ASIGNACIÓN RÁPIDA (MODAL)
// =========================================================================
const modalAsignacionAbierto = ref(false);
const diaSeleccionado = ref(null);

const abrirModalAsignacion = (celda) => {
    if (celda.esRelleno || !props.permisos.es_jefe) return;
    diaSeleccionado.value = celda;
    
    formManual.fecha = celda.fecha_vence;
    formManual.user_id = celda.user_id || '';
    formManual.tipo = celda.tipo || 'diaria_17h';
    
    modalAsignacionAbierto.value = true;
};

const guardarGuardiaDesdeCalendario = () => {
    formManual.post('/guardias/manual', {
        preserveScroll: true,
        onSuccess: () => {
            modalAsignacionAbierto.value = false;
            formManual.reset('user_id', 'fecha');
            diaSeleccionado.value = null;
        }
    });
};

// =========================================================================
// MATRIZ DE CALENDARIO FIJA (Soporte Multi-Médico)
// =========================================================================
const diasMatriz = computed(() => {
    const year = anioFiltro.value;
    const month = mesFiltro.value;
    
    const primerDia = new Date(year, month - 1, 1);
    let desfaseInicial = primerDia.getDay(); 
    desfaseInicial = desfaseInicial === 0 ? 6 : desfaseInicial - 1; 
    
    const totalDias = new Date(year, month, 0).getDate();
    const matrizCompleta = [];
    
    for (let i = 0; i < desfaseInicial; i++) { 
        matrizCompleta.push({ esRelleno: true }); 
    }
    
    const mapaGuardias = {};
    if (props.guardias) {
        props.guardias.forEach(g => {
            const partes = g.fecha_formateada ? g.fecha_formateada.split('/') : [];
            if (partes.length > 0) {
                const diaNum = parseInt(partes[0]);
                if (!mapaGuardias[diaNum]) mapaGuardias[diaNum] = [];
                mapaGuardias[diaNum].push(g);
            }
        });
    }
    
    for (let d = 1; d <= totalDias; d++) {
        const fechaObj = new Date(year, month - 1, d);
        const dayOfWeek = fechaObj.getDay(); 
        const esFinde = (dayOfWeek === 0 || dayOfWeek === 6);
        
        const mm = String(month).padStart(2, '0');
        const dd = String(d).padStart(2, '0');
        const fechaString = `${year}-${mm}-${dd}`;
        
        if (mapaGuardias[d] && mapaGuardias[d].length > 0) {
            matrizCompleta.push({
                esRelleno: false,
                numero: d,
                es_finde: esFinde,
                fecha_vence: fechaString,
                tiene_guardia: true,
                guardias_dia: mapaGuardias[d]
            });
        } else {
            matrizCompleta.push({
                esRelleno: false,
                numero: d,
                es_finde: esFinde,
                fecha_vence: fechaString,
                tiene_guardia: false,
                guardias_dia: []
            });
        }
    }
    return matrizCompleta;
});
</script>

<template>
    <Head title="Inteligencia de Turnos" />

    <div class="space-y-4 sm:space-y-6 relative max-w-full overflow-hidden">
        
        <!-- ALERTA DE CONFLICTO / ERROR DE PERMUTA / MANUAL -->
        <div v-if="$page.props.errors.conflicto" class="p-4 bg-rose-500/20 border-2 border-rose-500/50 rounded-xl sm:rounded-2xl flex items-center gap-3 text-rose-200 animate-in fade-in zoom-in">
            <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
            <div>
                <span class="font-bold text-rose-400 uppercase text-[10px] sm:text-xs tracking-wider block">Bloqueo de Seguridad</span>
                <p class="text-xs sm:text-sm font-medium mt-0.5">{{ $page.props.errors.conflicto }}</p>
            </div>
            <button @click="$page.props.errors.conflicto = null" class="ml-auto p-1 hover:bg-rose-500/20 rounded-lg text-rose-400 cursor-pointer transition-colors">
                <X class="w-5 h-5"/>
            </button>
        </div>

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

        <div class="bg-gradient-to-r from-zinc-900 via-zinc-900 to-emerald-950/40 border border-zinc-800 rounded-xl sm:rounded-2xl shadow-xl overflow-hidden">
            <div class="p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 sm:gap-6">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] sm:text-xs font-mono font-bold">
                        <Cpu class="w-3.5 h-3.5 animate-spin" /> MOTOR CSP v3.0 (RESTRICCIONES DINÁMICAS)
                    </div>
                    <h2 class="text-lg sm:text-xl font-bold text-white tracking-tight">Planificador Sanitario Automatizado</h2>
                </div>

                <div v-if="permisos.es_jefe" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 shrink-0">
                    <button @click="nuclearVaciarMes" class="px-4 py-2.5 rounded-lg border border-rose-500/30 text-rose-400 hover:bg-rose-500/10 transition-all font-bold text-xs sm:text-sm flex justify-center items-center gap-2 cursor-pointer">
                        <Trash2 class="w-4 h-4" /> Limpiar
                    </button>
                    <button @click="dispararAlgoritmo" :disabled="formGenerador.processing" class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-xs sm:text-sm transition-all shadow-lg flex justify-center items-center gap-2 disabled:opacity-50 cursor-pointer">
                        <Sparkles class="w-4 h-4 fill-zinc-950" />
                        {{ formGenerador.processing ? 'Resolviendo...' : 'Generar IA' }}
                    </button>
                    <div class="flex items-center gap-2">
                        <a :href="`/guardias/exportar/excel?mes=${mesFiltro}&anio=${anioFiltro}`" class="flex-1 text-center px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 font-bold text-xs border border-emerald-500/30">📊 Excel</a>
                        <a :href="`/guardias/exportar/pdf?mes=${mesFiltro}&anio=${anioFiltro}`" class="flex-1 text-center px-3 py-2 rounded-lg bg-rose-500/10 text-rose-400 font-bold text-xs border border-rose-500/30">📄 PDF</a>
                    </div>
                </div>
            </div>

            <div v-if="permisos.es_jefe" class="border-t border-zinc-800/80 bg-zinc-950/60 p-4 sm:p-6 space-y-4">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-zinc-800/60">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="formGenerador.usar_plantilla_completa" class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-zinc-950">
                        <span class="text-sm font-semibold text-zinc-200 flex items-center gap-2">
                            <Settings2 class="w-4 h-4 text-emerald-400" /> Usar plantilla completa para este mes
                        </span>
                    </label>
                </div>

                <div v-if="!formGenerador.usar_plantilla_completa" class="pt-1 pb-3 border-b border-zinc-800/60 animate-in fade-in">
                    <span class="block text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3">Facultativos incluidos en esta corrida:</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                        <label v-for="m in medicos" :key="m.id" class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors" :class="formGenerador.medicos_incluidos.includes(m.id) ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-zinc-900 border-zinc-800 text-zinc-500'">
                            <input type="checkbox" :value="m.id" v-model="formGenerador.medicos_incluidos" class="hidden">
                            <CheckCircle2 class="w-4 h-4" :class="formGenerador.medicos_incluidos.includes(m.id) ? 'opacity-100' : 'opacity-0'" />
                            <span class="text-xs font-bold truncate">{{ m.name }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-bold text-emerald-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <Sliders class="w-3.5 h-3.5" /> Reglas y Parámetros del Algoritmo:
                    </span>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">

                        <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                            <label class="block text-xs font-bold text-white mb-1">Simultáneos</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" max="10" v-model="formGenerador.personas_por_dia" class="w-16 bg-zinc-950 border border-zinc-700 text-emerald-400 rounded px-2 py-1 text-xs font-bold font-mono outline-none focus:border-emerald-500">
                                <span class="text-[10px] text-zinc-500">(Puestos / día)</span>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="formGenerador.respetar_salientes" class="w-4 h-4 rounded border-zinc-700 bg-zinc-950 text-emerald-500 focus:ring-emerald-500">
                                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <Moon class="w-3.5 h-3.5 text-indigo-400" /> Salientes
                                </span>
                            </label>
                            <div v-if="formGenerador.respetar_salientes" class="pl-6 text-[11px] text-zinc-400 flex items-center gap-2">
                                <select v-model="formGenerador.distancia_minima_dias" class="bg-zinc-950 border border-zinc-700 text-emerald-400 rounded px-1.5 py-0.5 text-xs font-bold outline-none focus:border-emerald-500">
                                    <option :value="1">24h libres</option>
                                    <option :value="2">48h libres</option>
                                    <option :value="3">72h libres</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                            <label class="block text-[10px] font-bold text-white mb-1">Max Totales</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" max="15" v-model="formGenerador.max_guardias_mes" class="w-16 bg-zinc-950 border border-zinc-700 text-emerald-400 rounded px-2 py-1 text-xs font-bold font-mono outline-none focus:border-emerald-500">
                            </div>
                        </div>

                        <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                            <label class="block text-[10px] font-bold text-white mb-1">Max Diarias / Findes</label>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] text-zinc-500 w-8">Lun-Vie:</span>
                                <input type="number" min="0" max="10" v-model="formGenerador.max_diarias_mes" class="w-12 bg-zinc-950 border border-zinc-700 text-emerald-400 rounded px-1.5 py-0.5 text-xs font-bold font-mono outline-none focus:border-emerald-500">
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-zinc-500 w-8">Findes:</span>
                                <input type="number" min="0" max="5" v-model="formGenerador.max_findes_mes" class="w-12 bg-zinc-950 border border-zinc-700 text-emerald-400 rounded px-1.5 py-0.5 text-xs font-bold font-mono outline-none focus:border-emerald-500">
                            </div>
                        </div>

                        <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2 col-span-1 md:col-span-2 lg:col-span-1">
                            <label class="flex items-center gap-2 cursor-pointer mb-2">
                                <input type="checkbox" v-model="formGenerador.prorratear_ausencias" class="w-4 h-4 rounded border-zinc-700 bg-zinc-950 text-emerald-500 focus:ring-emerald-500">
                                <span class="text-[10px] font-bold text-white leading-tight">Prorratear Ausencias</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="formGenerador.usar_memoria_anual" class="w-4 h-4 rounded border-zinc-700 bg-zinc-950 text-emerald-500 focus:ring-emerald-500">
                                <span class="text-[10px] font-bold text-white leading-tight">Memoria Anual YTD</span>
                            </label>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between bg-zinc-900 border border-zinc-800 p-2 rounded-xl shadow-sm gap-3">
            <div class="flex items-center justify-center sm:justify-start gap-2 px-2 text-zinc-400 font-bold text-[11px] sm:text-sm uppercase tracking-wider">
                <CalendarDays class="w-4 h-4 text-emerald-400" /> Consultando periodo:
            </div>
            
            <div class="flex items-center justify-center gap-1 w-full sm:w-auto">
                <button @click="mesAnterior" class="p-2 sm:p-2.5 bg-zinc-950 text-zinc-400 rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center cursor-pointer">
                    <ChevronLeft class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
                <select v-model="mesFiltro" @change="navegarMes" class="bg-zinc-950 text-emerald-400 font-bold text-xs sm:text-sm rounded-lg border-zinc-800 px-2 py-2 sm:px-3 text-center appearance-none outline-none">
                    <option :value="1">Ene</option><option :value="2">Feb</option><option :value="3">Mar</option>
                    <option :value="4">Abr</option><option :value="5">May</option><option :value="6">Jun</option>
                    <option :value="7">Jul</option><option :value="8">Ago</option><option :value="9">Sep</option>
                    <option :value="10">Oct</option><option :value="11">Nov</option><option :value="12">Dic</option>
                </select>
                <select v-model="anioFiltro" @change="navegarMes" class="bg-zinc-950 text-emerald-400 font-bold text-xs sm:text-sm rounded-lg border-zinc-800 px-2 py-2 sm:px-3 text-center appearance-none outline-none">
                    <option v-for="a in aniosDisponibles" :key="a" :value="a">{{ a }}</option>
                </select>
                <button @click="mesSiguiente" class="p-2 sm:p-2.5 bg-zinc-950 text-zinc-400 rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center cursor-pointer">
                    <ChevronRight class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
            </div>
        </div>

        <div class="flex overflow-x-auto gap-1 sm:gap-2 border-b border-zinc-800 pb-px hide-scrollbar">
            <button @click="pestanaActual = 'cuadrante'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer" :class="pestanaActual === 'cuadrante' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <BarChart3 class="w-3.5 h-3.5" /> Equidad
            </button>
            <button @click="pestanaActual = 'calendario'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer" :class="pestanaActual === 'calendario' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <CalendarDays class="w-3.5 h-3.5" /> Cuadrícula
            </button>
            <button @click="pestanaActual = 'reglas'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer" :class="pestanaActual === 'reglas' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <Ban class="w-3.5 h-3.5" /> Reglas ({{ totalReglasVisibles }})
            </button>
            <button v-if="permisos.es_jefe" @click="pestanaActual = 'manual'" class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer" :class="pestanaActual === 'manual' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-zinc-400'">
                <Pin class="w-3.5 h-3.5" /> Manual
            </button>
        </div>

        <!-- Pestaña 1: Cuadrante Equidad -->
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
                            <td class="py-2 sm:py-3.5 px-4 sm:px-6 font-semibold flex items-center gap-2">
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
                <p class="text-sm text-zinc-500">No hay guardias registradas. Ejecuta la IA o haz clic en la cuadrícula.</p>
            </div>
        </div>

        <!-- Pestaña 2: Calendario Cuadrícula -->
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
                    @click="abrirModalAsignacion(celda)"
                    class="min-h-[85px] sm:min-h-[125px] rounded-md sm:rounded-xl border p-1 flex flex-col transition-all group relative overflow-hidden"
                    :class="[
                        celda.esRelleno ? 'bg-zinc-950/20 border-zinc-900/40 opacity-20' : 'bg-zinc-900 border-zinc-800 shadow-sm', 
                        !celda.esRelleno && permisos.es_jefe ? 'cursor-pointer hover:border-zinc-700 hover:bg-zinc-900/80' : '',
                        celda.es_finde ? 'bg-gradient-to-b from-zinc-900 to-amber-950/10 border-amber-500/10' : ''
                    ]"
                >
                    <div v-if="!celda.esRelleno" class="flex justify-between items-center z-10 relative mb-1 px-1">
                        <span class="font-mono text-xs sm:text-sm font-black" :class="celda.es_finde ? 'text-amber-400' : 'text-zinc-500'">{{ celda.numero }}</span>
                        <span class="hidden sm:inline text-[8px] sm:text-[9px] font-mono text-zinc-600 uppercase">{{ celda.es_finde ? '24h' : '17h' }}</span>
                    </div>

                    <div v-if="!celda.esRelleno" class="flex-1 flex flex-col gap-1 z-10 relative overflow-y-auto hide-scrollbar">
                        <div v-if="celda.tiene_guardia">
                            <div v-for="g in celda.guardias_dia" :key="g.id" class="w-full p-1 rounded border text-center flex flex-col items-center justify-center min-h-[30px] mb-1 shadow-sm transition-all relative group/item" :class="[g.is_manual ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' : (celda.es_finde ? 'bg-zinc-950/50 text-amber-200 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20')]">
                                <div class="flex items-center gap-0.5 sm:gap-1 justify-center w-full">
                                    <span class="block truncate text-[8px] sm:text-[10px] font-black uppercase">{{ g.facultativo.replace('Dr. ', '').replace('Dra. ', '') }}</span>
                                    <Pin v-if="g.is_manual" class="hidden sm:block w-3 h-3 text-amber-400 shrink-0" />
                                </div>
                                
                                <div v-if="permisos.es_jefe" class="absolute inset-0 bg-zinc-900/90 flex items-center justify-center gap-2 opacity-0 group-hover/item:opacity-100 transition-opacity rounded" @click.stop>
                                    <button @click="iniciarOEjecutarPermuta(g)" type="button" class="p-1 text-zinc-400 hover:text-blue-400 rounded hover:bg-zinc-800 flex items-center justify-center cursor-pointer"><ArrowLeftRight class="w-3 h-3" /></button>
                                    <button @click="borrarGuardia(g.id)" type="button" class="p-1 text-zinc-400 hover:text-rose-400 rounded hover:bg-zinc-800 flex items-center justify-center cursor-pointer"><Trash2 class="w-3 h-3" /></button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="permisos.es_jefe && (!celda.tiene_guardia || celda.guardias_dia.length < 3)" class="w-full py-1 border border-dashed border-zinc-800/60 rounded flex items-center justify-center text-[10px] text-zinc-600 opacity-0 group-hover:opacity-100 transition-opacity mt-auto">
                            + Asignar
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestaña 3: Reglas de la IA -->
        <div v-if="pestanaActual === 'reglas'" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 animate-in fade-in duration-200">
            
            <div v-if="permisos.es_jefe" class="p-4 sm:p-6 bg-zinc-900 border border-zinc-800 rounded-xl h-fit">
                <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2 mb-4"><Ban class="w-4 h-4 text-rose-400" /> Añadir veto o restricción</h3>
                <form @submit.prevent="guardarRegla" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">Facultativo implicado</label>
                        <select v-model="formLimitacion.user_id" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 outline-none focus:border-emerald-500">
                            <option v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">Tipo de Regla</label>
                        <select v-model="formLimitacion.tipo" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 outline-none focus:border-emerald-500">
                            <option value="dia_semana">Día semana (recurrente)</option>
                            <option value="fecha_concreta">Día exacto</option>
                            <option value="periodo">Rango de días</option>
                        </select>
                    </div>

                    <!-- Input Dinámico según Selección -->
                    <div v-if="formLimitacion.tipo === 'dia_semana'">
                        <select v-model="formLimitacion.valor" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 outline-none focus:border-emerald-500">
                            <option value="1">Lunes</option><option value="2">Martes</option><option value="3">Miércoles</option><option value="4">Jueves</option><option value="5">Viernes</option><option value="6">Sábado</option><option value="7">Domingo</option>
                        </select>
                    </div>
                    
                    <div v-else-if="formLimitacion.tipo === 'fecha_concreta'">
                        <input v-model="formLimitacion.valor" type="date" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 font-mono outline-none focus:border-emerald-500" />
                    </div>

                    <div v-else-if="formLimitacion.tipo === 'periodo'" class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="block text-[9px] text-zinc-500 mb-0.5">Desde</span>
                            <input v-model="formLimitacion.fecha_inicio" type="date" required class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 font-mono outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <span class="block text-[9px] text-zinc-500 mb-0.5">Hasta</span>
                            <input v-model="formLimitacion.fecha_fin" type="date" required class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 font-mono outline-none focus:border-emerald-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">Justificación (Opcional)</label>
                        <input v-model="formLimitacion.motivo" type="text" placeholder="Ej: Reducción de jornada" class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-2.5 outline-none focus:border-emerald-500" />
                    </div>

                    <button type="submit" :disabled="formLimitacion.processing" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold rounded-lg transition-colors cursor-pointer mt-2 disabled:opacity-50">
                        Registrar Regla
                    </button>
                </form>
            </div>

            <div :class="permisos.es_jefe ? 'lg:col-span-2' : 'lg:col-span-3'" class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 sm:p-6 space-y-8">
                
                <!-- Sección 1: Reglas Recurrentes -->
                <div>
                    <h3 class="font-bold text-white mb-4 text-sm sm:text-base flex items-center gap-2">
                        <User class="w-4 h-4 text-emerald-400" /> Patrones Recurrentes
                    </h3>
                    <div v-if="limitacionesRecurrentes.length === 0" class="text-xs text-zinc-500 italic p-4 text-center border border-dashed border-zinc-800 rounded-lg">
                        No hay reglas recurrentes activas.
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="regla in limitacionesRecurrentes" :key="regla.id" class="p-3 bg-zinc-950 rounded-lg border border-zinc-800 flex flex-col sm:flex-row justify-between sm:items-center text-xs sm:text-sm gap-2">
                            <div>
                                <span class="text-emerald-400 font-bold">{{ regla.medico }}</span> 
                                <span class="text-zinc-400 ml-1">prohibido: <u class="text-zinc-200 font-mono bg-zinc-900 px-1 py-0.5 rounded">{{ regla.regla }}</u></span>
                            </div>
                            <button v-if="permisos.es_jefe" @click="router.delete(`/guardias/limitaciones/${regla.id}`)" class="text-rose-400 bg-rose-500/10 p-1.5 rounded self-end sm:self-auto cursor-pointer"><Trash2 class="w-3.5 h-3.5" /></button>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Reglas Mensuales y Periodos -->
                <div>
                    <h3 class="font-bold text-white mb-4 text-sm sm:text-base flex items-center gap-2">
                        <CalendarDays class="w-4 h-4 text-amber-400" /> Días Exactos y Periodos
                    </h3>
                    <div v-if="limitacionesMensuales.length === 0" class="text-xs text-zinc-500 italic p-4 text-center border border-dashed border-zinc-800 rounded-lg">
                        Ninguna restricción puntual o de rango registrada.
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="regla in limitacionesMensuales" :key="regla.id" class="p-3 bg-zinc-950 rounded-lg border border-zinc-800 flex flex-col sm:flex-row justify-between sm:items-center text-xs sm:text-sm gap-2">
                            <div>
                                <span class="text-emerald-400 font-bold">{{ regla.medico }}</span> 
                                <span class="text-zinc-400 ml-1">prohibido: <u class="text-zinc-200 font-mono bg-zinc-900 px-1 py-0.5 rounded">{{ regla.regla }}</u></span>
                                <span v-if="regla.motivo" class="block text-[10px] text-zinc-500 mt-1 italic">"{{ regla.motivo }}"</span>
                            </div>
                            <button v-if="permisos.es_jefe" @click="router.delete(`/guardias/limitaciones/${regla.id}`)" class="text-rose-400 bg-rose-500/10 p-1.5 rounded self-end sm:self-auto cursor-pointer"><Trash2 class="w-3.5 h-3.5" /></button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div v-if="pestanaActual === 'manual' && permisos.es_jefe" class="max-w-md w-full bg-zinc-900 p-4 sm:p-6 rounded-xl border border-zinc-800 mx-auto animate-in fade-in">
            <form @submit.prevent="guardarGuardiaManual" class="space-y-4">
                <select v-model="formManual.user_id" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none">
                    <option value="" disabled selected>Facultativo...</option>
                    <option v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <input v-model="formManual.fecha" required type="date" class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 font-mono text-xs sm:text-sm outline-none" />
                <select v-model="formManual.tipo" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none">
                    <option value="diaria_17h">Diario (17h)</option>
                    <option value="festivo_24h">Festivo (24h)</option>
                </select>
                <button type="submit" :disabled="formManual.processing" class="w-full py-3 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black rounded-xl text-xs flex justify-center gap-2 cursor-pointer disabled:opacity-50">
                    <Pin class="w-4 h-4" /> Fijar Turno
                </button>
            </form>
        </div>

        <div v-if="modalAsignacionAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in">
            <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-md w-full overflow-hidden shadow-2xl animate-in slide-in-from-top-4">
                <div class="p-4 bg-zinc-950 border-b border-zinc-800 flex justify-between items-center">
                    <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2">
                        <Pin class="w-4 h-4 text-amber-400" /> Asignar Guardia — Día {{ diaSeleccionado?.numero }}
                    </h3>
                    <button @click="modalAsignacionAbierto = false" class="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-800 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-6">
                    <form @submit.prevent="guardarGuardiaDesdeCalendario" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Facultativo Asignado</label>
                            <select v-model="formManual.user_id" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none">
                                <option value="" disabled selected>Selecciona un facultativo...</option>
                                <option v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Tipo de Guardia</label>
                            <select v-model="formManual.tipo" required class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none">
                                <option value="diaria_17h">Diario (17h)</option>
                                <option value="festivo_24h">Festivo (24h)</option>
                            </select>
                        </div>
                        <div class="pt-2 flex gap-2">
                            <button type="button" @click="modalAsignacionAbierto = false" class="flex-1 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold rounded-xl text-xs transition-colors cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="formManual.processing" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black rounded-xl text-xs flex justify-center items-center gap-2 transition-colors cursor-pointer disabled:opacity-50">
                                <Pin class="w-4 h-4" /> Fijar Turno
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
.animate-in { animation: fadeIn 0.2s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: translateY(0); } }
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>