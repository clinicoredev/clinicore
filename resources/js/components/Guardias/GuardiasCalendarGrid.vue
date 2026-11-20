<script setup lang="ts">
import type {
    CeldaCalendario,
    DiagnosticoConflictoResultado,
    GuardiaItem,
    MedicoItem,
} from '@/composables/useGuardiasCalendar';
import {
    AlertTriangle,
    ArrowLeftRight,
    Ban,
    CalendarDays,
    CheckCircle2,
    ListFilter,
    Pin,
    ShieldAlert,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    variante: 'adjuntos' | 'residentes';
    esJefe: boolean;
    diasMatriz: CeldaCalendario[];
    medicos: MedicoItem[];
    modalAsignacionAbierto: boolean;
    diaSeleccionado: CeldaCalendario | null;
    formManual: Record<string, any>;
    cargandoConflictoManual?: boolean;
    diagnosticoManual?: DiagnosticoConflictoResultado | null;
    guardiaOrigen?: GuardiaItem | null;
    guardiaDestinoPermuta?: GuardiaItem | null;
    modalPermutaConflictoAbierto?: boolean;
    cargandoConflictoPermuta?: boolean;
    diagnosticoPermuta?: DiagnosticoConflictoResultado | null;
    tituloModalFijar: string;
    labelSelectorModal: string;
    placeholderSelectorModal: string;
}>();

const emit = defineEmits<{
    (e: 'abrirModalAsignacion', celda: CeldaCalendario): void;
    (e: 'cerrarModalAsignacion'): void;
    (e: 'guardarGuardiaManual'): void;
    (e: 'iniciarOEjecutarPermuta', guardia: GuardiaItem): void;
    (e: 'arrastrarPermuta', origen: GuardiaItem, destino: GuardiaItem): void;
    (e: 'confirmarPermutaVerificada'): void;
    (e: 'cerrarModalPermutaConflicto'): void;
    (e: 'borrarGuardia', id: number): void;
}>();

const guardiaArrastrada = ref<GuardiaItem | null>(null);
const modoVistaCalendario = ref<'cuadricula' | 'agenda'>('cuadricula');

const diasConContenidoAgenda = computed(() =>
    props.diasMatriz.filter((c) => !c.esRelleno),
);

const onDragStartGuardia = (g: GuardiaItem) => {
    if (!props.esJefe) {
        return;
    }
    guardiaArrastrada.value = g;
};

const onDropSobreGuardia = (destino: GuardiaItem) => {
    if (!props.esJefe || !guardiaArrastrada.value) {
        return;
    }
    if (guardiaArrastrada.value.id !== destino.id) {
        emit('arrastrarPermuta', guardiaArrastrada.value, destino);
    }
    guardiaArrastrada.value = null;
};
</script>

<template>
    <div class="space-y-2 sm:space-y-4 animate-in fade-in duration-200">
        <!-- CONMUTADOR VISTA CUADRÍCULA / AGENDA MÓVIL (F6-07) -->
        <div class="flex items-center justify-between gap-2 bg-zinc-950/70 p-2 rounded-xl border border-zinc-800/70">
            <span class="text-[11px] text-zinc-400 font-medium pl-1">
                Visualización del cuadrante mensual
            </span>
            <div class="inline-flex rounded-lg bg-zinc-900 p-0.5 border border-zinc-800">
                <button
                    type="button"
                    @click="modoVistaCalendario = 'cuadricula'"
                    class="px-2.5 py-1 rounded-md text-[11px] font-bold flex items-center gap-1.5 cursor-pointer transition-colors"
                    :class="modoVistaCalendario === 'cuadricula' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white'"
                >
                    <CalendarDays class="w-3.5 h-3.5" /> Cuadrícula
                </button>
                <button
                    type="button"
                    @click="modoVistaCalendario = 'agenda'"
                    class="px-2.5 py-1 rounded-md text-[11px] font-bold flex items-center gap-1.5 cursor-pointer transition-colors"
                    :class="modoVistaCalendario === 'agenda' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white'"
                >
                    <ListFilter class="w-3.5 h-3.5" /> Agenda / Móvil
                </button>
            </div>
        </div>

        <template v-if="modoVistaCalendario === 'cuadricula'">
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
                @click="emit('abrirModalAsignacion', celda)"
                class="min-h-[90px] sm:min-h-[130px] rounded-md sm:rounded-xl border p-1.5 flex flex-col transition-all group relative overflow-hidden"
                :class="[
                    celda.esRelleno ? 'bg-zinc-950/20 border-zinc-900/40 opacity-20' : 'bg-zinc-900 border-zinc-800 shadow-sm',
                    !celda.esRelleno && esJefe ? 'cursor-pointer hover:border-zinc-700 hover:bg-zinc-900/80' : '',
                    celda.es_finde ? 'bg-gradient-to-b from-zinc-900 to-amber-950/15 border-amber-500/20' : ''
                ]"
            >
                <div v-if="!celda.esRelleno" class="flex justify-between items-center z-10 relative mb-1 px-0.5">
                    <span
                        class="font-mono text-xs sm:text-sm font-black"
                        :class="celda.es_festivo ? 'text-rose-400' : (celda.es_finde ? 'text-amber-400' : 'text-zinc-500')"
                    >
                        {{ celda.numero }}
                    </span>
                    <span class="hidden sm:inline text-[8px] sm:text-[9px] font-mono text-zinc-600 uppercase">
                        {{ celda.es_finde ? '24h' : '17h' }}
                    </span>
                </div>

                <div v-if="!celda.esRelleno" class="flex-1 flex flex-col gap-1 z-10 relative overflow-y-auto hide-scrollbar">
                    <!-- DIFERENCIACIÓN VISUAL DE FESTIVOS Y [ESPECIAL] -->
                    <div
                        v-if="celda.es_festivo"
                        class="text-[8px] sm:text-[9px] font-bold px-1 py-0.5 rounded text-center truncate w-full border"
                        :class="celda.es_festivo_especial ? 'text-amber-300 bg-amber-500/20 border-amber-500/40' : 'text-rose-400 bg-rose-500/10 border-rose-500/20'"
                    >
                        {{ celda.es_festivo_especial ? '★ ' : '🎉 ' }}{{ celda.nombre_festivo || 'Festivo' }}
                    </div>

                    <!-- REGLAS, AUSENCIAS, ROTACIONES Y SALIENTES TRASLADADOS DEL DÍA -->
                    <div v-if="celda.vetos_dia && celda.vetos_dia.length > 0" class="flex flex-col gap-0.5 mb-0.5">
                        <div
                            v-for="veto in celda.vetos_dia"
                            :key="veto.id"
                            class="px-1 py-0.5 rounded text-[8px] sm:text-[9px] font-bold flex items-center gap-1 truncate border"
                            :class="veto.es_saliente_lunes
                                ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'
                                : (veto.es_rotacion_guardias
                                    ? 'bg-blue-500/10 text-blue-400 border-blue-500/20'
                                    : (veto.es_ausencia
                                        ? 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                        : 'bg-orange-500/10 text-orange-400 border-orange-500/20'))"
                            :title="veto.motivo"
                        >
                            <span v-if="veto.es_saliente_lunes" class="shrink-0 font-mono">↪ Saliente:</span>
                            <Ban v-else-if="!veto.es_rotacion_guardias" class="w-2.5 h-2.5 shrink-0" />
                            {{ veto.medico.replace('Dr. ', '').replace('Dra. ', '') }}
                        </div>
                    </div>

                    <!-- TURNOS DE GUARDIA -->
                    <div v-if="celda.tiene_guardia && celda.guardias_dia">
                        <div
                            v-for="g in celda.guardias_dia"
                            :key="g.id"
                            :draggable="esJefe"
                            @dragstart.stop="onDragStartGuardia(g)"
                            @dragover.prevent
                            @drop.stop="onDropSobreGuardia(g)"
                            class="w-full p-1 rounded border text-center flex flex-col items-center justify-center min-h-[30px] mb-1 shadow-sm transition-all relative group/item"
                            :class="[
                                g.is_manual
                                    ? 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                    : (celda.es_finde
                                        ? 'bg-zinc-950/60 text-amber-200 border-amber-500/20'
                                        : (variante === 'residentes'
                                            ? 'bg-purple-500/10 text-purple-400 border-purple-500/20'
                                            : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'))
                            ]"
                        >
                            <div class="flex items-center gap-0.5 sm:gap-1 justify-center w-full">
                                <span class="block truncate text-[8px] sm:text-[10px] font-black uppercase">
                                    {{ g.facultativo.replace('Dr. ', '').replace('Dra. ', '') }}
                                </span>
                                <span
                                    v-if="g.modalidad === 'localizada'"
                                    class="px-1 py-0.2 rounded text-[7px] sm:text-[8px] font-mono bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shrink-0"
                                    title="Guardia Localizada"
                                >
                                    Loc.
                                </span>
                                <Pin v-if="g.is_manual" class="hidden sm:block w-3 h-3 text-amber-400 shrink-0" />
                            </div>

                            <div
                                v-if="esJefe"
                                class="absolute inset-0 bg-zinc-900/95 flex items-center justify-center gap-2 opacity-0 group-hover/item:opacity-100 transition-opacity rounded"
                                @click.stop
                            >
                                <button
                                    @click="emit('iniciarOEjecutarPermuta', g)"
                                    type="button"
                                    title="Permutar turno"
                                    class="p-1 text-zinc-400 hover:text-blue-400 rounded hover:bg-zinc-800 flex items-center justify-center cursor-pointer"
                                >
                                    <ArrowLeftRight class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    @click="emit('borrarGuardia', g.id)"
                                    type="button"
                                    title="Eliminar turno"
                                    class="p-1 text-zinc-400 hover:text-rose-400 rounded hover:bg-zinc-800 flex items-center justify-center cursor-pointer"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="esJefe && (!celda.tiene_guardia || (celda.guardias_dia && celda.guardias_dia.length < 5))"
                        class="w-full py-1 border border-dashed border-zinc-800/60 rounded flex items-center justify-center text-[10px] text-zinc-600 opacity-0 group-hover:opacity-100 transition-opacity mt-auto"
                    >
                        + Asignar
                    </div>
                </div>
            </div>
        </div>
        </template>

        <!-- VISTA AGENDA / LISTA OPTIMIZADA PARA MÓVIL (F6-07) -->
        <div v-else class="space-y-2">
            <div
                v-for="celda in diasConContenidoAgenda"
                :key="celda.fecha_vence || celda.numero"
                @click="emit('abrirModalAsignacion', celda)"
                class="p-3 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 transition-colors"
                :class="[
                    celda.es_finde ? 'bg-zinc-900/90 border-amber-500/30' : 'bg-zinc-900 border-zinc-800',
                    esJefe ? 'cursor-pointer hover:border-zinc-700' : ''
                ]"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl flex flex-col items-center justify-center font-mono font-black text-sm border shrink-0"
                        :class="celda.es_festivo
                            ? 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                            : (celda.es_finde ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' : 'bg-zinc-950 text-zinc-300 border-zinc-800')"
                    >
                        <span>{{ celda.numero }}</span>
                        <span class="text-[8px] uppercase font-normal">{{ celda.es_finde ? '24h' : '17h' }}</span>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs font-bold text-white font-mono">{{ celda.fecha_vence }}</span>
                            <span
                                v-if="celda.es_festivo"
                                class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                :class="celda.es_festivo_especial ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-rose-500/15 text-rose-300 border-rose-500/30'"
                            >
                                {{ celda.es_festivo_especial ? '★ ' : '🎉 ' }}{{ celda.nombre_festivo || 'Festivo' }}
                            </span>
                        </div>
                        <div v-if="celda.vetos_dia && celda.vetos_dia.length > 0" class="flex flex-wrap gap-1 mt-1">
                            <span
                                v-for="veto in celda.vetos_dia"
                                :key="veto.id"
                                class="text-[9px] px-1.5 py-0.5 rounded border"
                                :class="veto.es_saliente_lunes
                                    ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'
                                    : 'bg-rose-500/10 text-rose-300 border-rose-500/20'"
                            >
                                {{ veto.es_saliente_lunes ? '↪ Saliente: ' : 'Veto: ' }}{{ veto.medico.replace('Dr. ', '').replace('Dra. ', '') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="celda.guardias_dia && celda.guardias_dia.length > 0">
                        <div
                            v-for="g in celda.guardias_dia"
                            :key="g.id"
                            class="px-2.5 py-1.5 rounded-lg border text-xs font-bold flex items-center gap-2"
                            :class="g.is_manual
                                ? 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20'"
                            @click.stop
                        >
                            <span>{{ g.facultativo }}</span>
                            <span
                                v-if="g.modalidad === 'localizada'"
                                class="px-1.5 py-0.5 rounded text-[9px] font-mono bg-indigo-500/20 text-indigo-300 border border-indigo-500/30"
                            >
                                Localizada
                            </span>
                            <button
                                v-if="esJefe"
                                type="button"
                                @click="emit('iniciarOEjecutarPermuta', g)"
                                class="p-1 text-zinc-400 hover:text-blue-400 cursor-pointer"
                                title="Permutar"
                            >
                                <ArrowLeftRight class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-if="esJefe"
                                type="button"
                                @click="emit('borrarGuardia', g.id)"
                                class="p-1 text-zinc-400 hover:text-rose-400 cursor-pointer"
                                title="Eliminar"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </template>
                    <span v-else class="text-xs text-zinc-500 italic">Sin guardia asignada</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE ASIGNACIÓN MANUAL DESDE CUADRÍCULA -->
    <div
        v-if="modalAsignacionAbierto"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in"
    >
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-md w-full overflow-hidden shadow-2xl animate-in slide-in-from-top-4">
            <div class="p-4 bg-zinc-950 border-b border-zinc-800 flex justify-between items-center">
                <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2">
                    <Pin class="w-4 h-4 text-amber-400" /> {{ tituloModalFijar }} — Día {{ diaSeleccionado?.numero }}
                </h3>
                <button
                    @click="emit('cerrarModalAsignacion')"
                    class="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-800 cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>
            <div class="p-6">
                <form @submit.prevent="emit('guardarGuardiaManual')" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">
                            {{ labelSelectorModal }}
                        </label>
                        <select
                            v-model="formManual.user_id"
                            required
                            class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none"
                        >
                            <option class="bg-zinc-900" value="" disabled selected>{{ placeholderSelectorModal }}</option>
                            <option class="bg-zinc-900" v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Tipo de Guardia</label>
                            <select
                                v-model="formManual.tipo"
                                required
                                class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none"
                            >
                                <option class="bg-zinc-900" value="diaria_17h">Diario (17h)</option>
                                <option class="bg-zinc-900" value="festivo_24h">Festivo / Finde (24h)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Modalidad</label>
                            <select
                                v-model="formManual.modalidad"
                                class="w-full bg-zinc-950 text-white p-2.5 rounded-xl border border-zinc-800 text-xs sm:text-sm outline-none"
                            >
                                <option class="bg-zinc-900" value="presencial">Presencial</option>
                                <option class="bg-zinc-900" value="localizada">Localizada</option>
                            </select>
                        </div>
                    </div>

                    <!-- DETECTOR DE CONFLICTOS EN TIEMPO REAL (F3-03) -->
                    <div v-if="formManual.user_id" class="space-y-2">
                        <div
                            v-if="cargandoConflictoManual"
                            class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-zinc-400 font-mono"
                        >
                            Evaluando conflictos de ausencias, limitaciones y salientes...
                        </div>
                        <div
                            v-else-if="diagnosticoManual && diagnosticoManual.valido"
                            class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-center gap-2"
                        >
                            <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                            <span>Asignación limpia: sin ausencias, vetos ni colisión de salientes.</span>
                        </div>
                        <div
                            v-else-if="diagnosticoManual && diagnosticoManual.conflictos.length > 0"
                            class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/40 space-y-1.5"
                        >
                            <div class="flex items-center gap-1.5 text-amber-300 font-bold text-xs">
                                <AlertTriangle class="w-4 h-4 shrink-0" />
                                <span>Conflicto detectado en tiempo real:</span>
                            </div>
                            <ul class="text-[11px] text-amber-200/90 space-y-1 pl-5 list-disc">
                                <li v-for="(c, idx) in diagnosticoManual.conflictos" :key="idx">
                                    {{ c.mensaje }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-1">
                        <input
                            type="checkbox"
                            v-model="formManual.forzar"
                            class="w-4 h-4 rounded border-zinc-700 bg-zinc-950 text-amber-500"
                        />
                        <span class="text-xs text-zinc-400">Forzar asignación (ignorar vetos puntuales)</span>
                    </label>
                    <div class="pt-2 flex gap-2">
                        <button
                            type="button"
                            @click="emit('cerrarModalAsignacion')"
                            class="flex-1 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold rounded-xl text-xs transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="formManual.processing"
                            class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-400 text-zinc-950 font-black rounded-xl text-xs flex justify-center items-center gap-2 transition-colors cursor-pointer disabled:opacity-50"
                        >
                            <Pin class="w-4 h-4" /> Fijar Turno
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETECTOR DE CONFLICTOS EN TIEMPO REAL PARA PERMUTAS (F3-03) -->
    <div
        v-if="modalPermutaConflictoAbierto && guardiaOrigen && guardiaDestinoPermuta"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in"
    >
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
            <div class="p-4 bg-zinc-950 border-b border-zinc-800 flex justify-between items-center">
                <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2">
                    <ArrowLeftRight class="w-4 h-4 text-indigo-400" />
                    Verificación de Permuta en Tiempo Real
                </h3>
                <button
                    type="button"
                    @click="emit('cerrarModalPermutaConflicto')"
                    class="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-800 cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[10px] font-bold uppercase text-zinc-500 block">Turno Origen</span>
                        <span class="font-bold text-white block mt-0.5">{{ guardiaOrigen.facultativo }}</span>
                        <span class="font-mono text-indigo-300 text-[11px]">{{ guardiaOrigen.fecha_formateada }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[10px] font-bold uppercase text-zinc-500 block">Turno Destino</span>
                        <span class="font-bold text-white block mt-0.5">{{ guardiaDestinoPermuta.facultativo }}</span>
                        <span class="font-mono text-indigo-300 text-[11px]">{{ guardiaDestinoPermuta.fecha_formateada }}</span>
                    </div>
                </div>

                <div
                    v-if="cargandoConflictoPermuta"
                    class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-zinc-400 font-mono text-center"
                >
                    Comprobando salientes, ausencias y limitaciones en ambos sentidos...
                </div>

                <div
                    v-else-if="diagnosticoPermuta && diagnosticoPermuta.valido"
                    class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3 text-xs text-emerald-200"
                >
                    <CheckCircle2 class="w-5 h-5 text-emerald-400 shrink-0" />
                    <div>
                        <span class="font-bold text-emerald-300 block">Intercambio 100% Viable</span>
                        <span>Ninguno de los profesionales presenta ausencias, vetos ni colisión de salientes en las fechas cruzadas.</span>
                    </div>
                </div>

                <div
                    v-else-if="diagnosticoPermuta && diagnosticoPermuta.conflictos.length > 0"
                    class="p-4 rounded-xl border space-y-2"
                    :class="diagnosticoPermuta.bloqueante
                        ? 'bg-rose-500/10 border-rose-500/40 text-rose-200'
                        : 'bg-amber-500/10 border-amber-500/40 text-amber-200'"
                >
                    <div class="flex items-center gap-2 font-bold text-xs">
                        <ShieldAlert v-if="diagnosticoPermuta.bloqueante" class="w-4 h-4 text-rose-400 shrink-0" />
                        <AlertTriangle v-else class="w-4 h-4 text-amber-400 shrink-0" />
                        <span>
                            {{ diagnosticoPermuta.bloqueante ? 'Permuta Bloqueada por Conflicto Clínico' : 'Advertencias Detectadas' }}
                        </span>
                    </div>
                    <ul class="text-xs space-y-1.5 pl-5 list-disc">
                        <li v-for="(c, i) in diagnosticoPermuta.conflictos" :key="i">
                            <strong class="text-white">{{ c.facultativo }}</strong> ({{ c.fecha_formateada }}): {{ c.mensaje }}
                        </li>
                    </ul>
                </div>

                <div class="pt-2 flex gap-2">
                    <button
                        type="button"
                        @click="emit('cerrarModalPermutaConflicto')"
                        class="flex-1 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold rounded-xl text-xs cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="cargandoConflictoPermuta || Boolean(diagnosticoPermuta?.bloqueante)"
                        @click="emit('confirmarPermutaVerificada')"
                        class="flex-1 py-2.5 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-black rounded-xl text-xs flex justify-center items-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <ArrowLeftRight class="w-4 h-4" /> Confirmar Intercambio
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
