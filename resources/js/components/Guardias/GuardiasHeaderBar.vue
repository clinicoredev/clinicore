<script setup lang="ts">
import type { GuardiaItem, MedicoItem } from '@/composables/useGuardiasCalendar';
import {
    ArrowLeftRight,
    Ban,
    BarChart3,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Filter,
    ShieldAlert,
    X,
} from '@lucide/vue';

defineProps<{
    variante: 'adjuntos' | 'residentes';
    tituloAvisoMotor: string;
    tituloModoPermuta: string;
    guardiaOrigen: GuardiaItem | null;
    medicos: MedicoItem[];
    etiquetaTodosFiltro: string;
    filtroMedicoVista: 'todos' | number;
    mesFiltro: number;
    anioFiltro: number;
    aniosDisponibles: number[];
    pestanaActual: 'cuadrante' | 'calendario' | 'reglas';
    totalReglasVisibles: number;
}>();

const emit = defineEmits<{
    (e: 'cancelarPermuta'): void;
    (e: 'update:filtroMedicoVista', value: 'todos' | number): void;
    (e: 'update:mesFiltro', value: number): void;
    (e: 'update:anioFiltro', value: number): void;
    (e: 'update:pestanaActual', value: 'cuadrante' | 'calendario' | 'reglas'): void;
    (e: 'navegarMes'): void;
    (e: 'mesAnterior'): void;
    (e: 'mesSiguiente'): void;
}>();
</script>

<template>
    <!-- ALERTA DE ERRORES -->
    <div
        v-if="$page.props.errors.conflicto || $page.props.errors.algoritmo"
        class="p-4 bg-rose-500/20 border-2 border-rose-500/50 rounded-xl sm:rounded-2xl flex items-center gap-3 text-rose-200 animate-in fade-in zoom-in"
    >
        <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
        <div>
            <span class="font-bold text-rose-400 uppercase text-[10px] sm:text-xs tracking-wider block">
                {{ tituloAvisoMotor }}
            </span>
            <p class="text-xs sm:text-sm font-medium mt-0.5">
                {{ $page.props.errors.conflicto || $page.props.errors.algoritmo }}
            </p>
        </div>
        <button
            @click="delete $page.props.errors.conflicto; delete $page.props.errors.algoritmo"
            class="ml-auto p-1 hover:bg-rose-500/20 rounded-lg text-rose-400 cursor-pointer transition-colors"
        >
            <X class="w-5 h-5" />
        </button>
    </div>

    <!-- BANNER PERMUTA -->
    <div
        v-if="guardiaOrigen"
        class="p-4 text-white rounded-xl sm:rounded-2xl flex flex-col sm:flex-row justify-between sm:items-center gap-3 shadow-lg animate-bounce"
        :class="variante === 'residentes' ? 'bg-gradient-to-r from-purple-600 to-indigo-600' : 'bg-gradient-to-r from-blue-600 to-indigo-600'"
    >
        <div class="flex items-center gap-3 text-xs">
            <ArrowLeftRight class="w-5 h-5 animate-pulse shrink-0" />
            <div>
                <span class="font-black uppercase tracking-wider block">{{ tituloModoPermuta }}</span>
                Selecciona el turno de destino para intercambiar con
                <strong class="underline">{{ guardiaOrigen.facultativo }}</strong>
                ({{ guardiaOrigen.fecha_formateada }}).
            </div>
        </div>
        <button
            @click="emit('cancelarPermuta')"
            class="p-2 w-full sm:w-auto bg-black/20 hover:bg-black/40 rounded-lg text-center flex justify-center transition-all cursor-pointer"
        >
            <X class="w-4 h-4" />
        </button>
    </div>

    <!-- SLOT PANEL GENERADOR -->
    <slot name="generator-panel" />

    <!-- BARRA DE PERIODO Y FILTRO POR MÉDICO/RESIDENTE -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between bg-zinc-900 border border-zinc-800 p-2.5 rounded-xl shadow-sm gap-3">
        <div class="flex items-center gap-3 px-2">
            <Filter
                class="w-4 h-4 shrink-0"
                :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
            />
            <select
                :value="filtroMedicoVista"
                @change="emit('update:filtroMedicoVista', ($event.target as HTMLSelectElement).value === 'todos' ? 'todos' : Number(($event.target as HTMLSelectElement).value))"
                class="bg-zinc-950 text-zinc-200 text-xs font-semibold rounded-lg border border-zinc-800 px-3 py-2 outline-none"
                :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
            >
                <option class="bg-zinc-900" value="todos">{{ etiquetaTodosFiltro }} ({{ medicos.length }})</option>
                <option class="bg-zinc-900" v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
        </div>

        <div class="flex items-center justify-center gap-1 w-full sm:w-auto">
            <button
                @click="emit('mesAnterior')"
                class="p-2 bg-zinc-950 text-zinc-400 hover:text-white rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center cursor-pointer"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>
            <select
                :value="mesFiltro"
                @change="emit('update:mesFiltro', Number(($event.target as HTMLSelectElement).value)); emit('navegarMes')"
                class="bg-zinc-950 font-bold text-xs sm:text-sm rounded-lg border border-zinc-800 px-3 py-2 text-center outline-none"
                :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
            >
                <option class="bg-zinc-900" :value="1">Enero</option>
                <option class="bg-zinc-900" :value="2">Febrero</option>
                <option class="bg-zinc-900" :value="3">Marzo</option>
                <option class="bg-zinc-900" :value="4">Abril</option>
                <option class="bg-zinc-900" :value="5">Mayo</option>
                <option class="bg-zinc-900" :value="6">Junio</option>
                <option class="bg-zinc-900" :value="7">Julio</option>
                <option class="bg-zinc-900" :value="8">Agosto</option>
                <option class="bg-zinc-900" :value="9">Septiembre</option>
                <option class="bg-zinc-900" :value="10">Octubre</option>
                <option class="bg-zinc-900" :value="11">Noviembre</option>
                <option class="bg-zinc-900" :value="12">Diciembre</option>
            </select>
            <select
                :value="anioFiltro"
                @change="emit('update:anioFiltro', Number(($event.target as HTMLSelectElement).value)); emit('navegarMes')"
                class="bg-zinc-950 font-bold text-xs sm:text-sm rounded-lg border border-zinc-800 px-3 py-2 text-center outline-none"
                :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
            >
                <option class="bg-zinc-900" v-for="a in aniosDisponibles" :key="a" :value="a">{{ a }}</option>
            </select>
            <button
                @click="emit('mesSiguiente')"
                class="p-2 bg-zinc-950 text-zinc-400 hover:text-white rounded-lg border border-zinc-800 flex-1 sm:flex-none flex justify-center cursor-pointer"
            >
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>
    </div>

    <!-- PESTAÑAS -->
    <div class="flex overflow-x-auto gap-1 sm:gap-2 border-b border-zinc-800 pb-px hide-scrollbar">
        <button
            @click="emit('update:pestanaActual', 'cuadrante')"
            class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer"
            :class="pestanaActual === 'cuadrante'
                ? (variante === 'residentes' ? 'border-purple-500 text-purple-400' : 'border-emerald-500 text-emerald-400')
                : 'border-transparent text-zinc-400'"
        >
            <BarChart3 class="w-3.5 h-3.5" /> Equidad y Listado
        </button>
        <button
            @click="emit('update:pestanaActual', 'calendario')"
            class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer"
            :class="pestanaActual === 'calendario'
                ? (variante === 'residentes' ? 'border-purple-500 text-purple-400' : 'border-emerald-500 text-emerald-400')
                : 'border-transparent text-zinc-400'"
        >
            <CalendarDays class="w-3.5 h-3.5" /> Cuadrícula Interactiva
        </button>
        <button
            @click="emit('update:pestanaActual', 'reglas')"
            class="whitespace-nowrap pb-3 px-3 sm:px-4 font-semibold text-[11px] sm:text-sm flex items-center gap-1.5 border-b-2 cursor-pointer"
            :class="pestanaActual === 'reglas'
                ? (variante === 'residentes' ? 'border-purple-500 text-purple-400' : 'border-emerald-500 text-emerald-400')
                : 'border-transparent text-zinc-400'"
        >
            <Ban class="w-3.5 h-3.5" /> Reglas y Vetos ({{ totalReglasVisibles }})
        </button>
    </div>
</template>
