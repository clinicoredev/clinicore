<script setup lang="ts">
import type { EquidadItem, GuardiaItem } from '@/composables/useGuardiasCalendar';
import { Pin } from '@lucide/vue';

defineProps<{
    variante: 'adjuntos' | 'residentes';
    tituloAuditoriaEquidad: string;
    columnaFacultativo: string;
    equidad: EquidadItem[];
    guardiasFiltradas: GuardiaItem[];
    filtroMedicoVista: 'todos' | number;
}>();

const emit = defineEmits<{
    (e: 'alternarFiltroMedico', medicoId: number): void;
}>();
</script>

<template>
    <div class="space-y-4 sm:space-y-6 animate-in fade-in duration-200">
        <div v-if="equidad.length > 0" class="p-4 sm:p-5 bg-zinc-900 border border-zinc-800 rounded-xl">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">
                {{ tituloAuditoriaEquidad }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div
                    v-for="eq in equidad"
                    :key="eq.id"
                    @click="emit('alternarFiltroMedico', eq.id)"
                    class="bg-zinc-950 p-3 rounded-xl border transition-all cursor-pointer font-mono"
                    :class="filtroMedicoVista === eq.id
                        ? (variante === 'residentes' ? 'border-purple-500 ring-1 ring-purple-500/30' : 'border-emerald-500 ring-1 ring-emerald-500/30')
                        : 'border-zinc-800 hover:border-zinc-700'"
                >
                    <div class="flex justify-between items-center gap-1">
                        <span class="text-zinc-200 font-bold truncate text-xs">{{ eq.nombre }}</span>
                        <div class="flex items-center gap-1 shrink-0">
                            <span
                                v-if="eq.porcentaje_jornada && eq.porcentaje_jornada < 100"
                                class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30"
                                title="Porcentaje de jornada activa"
                            >
                                {{ eq.porcentaje_jornada }}%
                            </span>
                            <span
                                v-if="eq.excede_tope_legal"
                                class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30"
                                title="Aviso informativo: alguna semana ISO supera 48h de guardia"
                            >
                                >48h/sem
                            </span>
                            <span
                                v-if="eq.horas_totales !== undefined"
                                class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-zinc-900 text-zinc-300 border border-zinc-700"
                            >
                                {{ eq.horas_totales }}h
                            </span>
                            <span
                                class="text-[10px] px-1.5 py-0.5 rounded font-bold border"
                                :class="variante === 'residentes'
                                    ? 'bg-purple-500/10 text-purple-400 border-purple-500/20'
                                    : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'"
                            >
                                {{ eq.puntos }} ptos
                            </span>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-t border-zinc-900 grid grid-cols-4 gap-1 text-[10px] text-center">
                        <div class="bg-zinc-900/60 p-1 rounded">
                            <span class="block text-zinc-500 text-[8px]">Disp.</span>
                            <b class="text-zinc-300">{{ eq.dias_disponibles }}d</b>
                        </div>
                        <div class="bg-zinc-900/60 p-1 rounded">
                            <span class="block text-zinc-500 text-[8px]">17h</span>
                            <b class="text-white">{{ eq.diarias }}</b>
                        </div>
                        <div class="bg-zinc-900/60 p-1 rounded">
                            <span class="block text-amber-500/80 text-[8px]">Finde</span>
                            <b class="text-amber-400">{{ eq.findes }}</b>
                        </div>
                        <div class="bg-zinc-900/60 p-1 rounded" title="Festivos este mes (Total Festivos en el Año / Especiales)">
                            <span class="block text-rose-500/80 text-[8px]">Fest (Año)</span>
                            <b class="text-rose-400">{{ eq.festivos }} <span class="text-zinc-400 font-normal">({{ eq.festivos_anual }})</span></b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="guardiasFiltradas.length > 0" class="bg-zinc-900 border border-zinc-800 rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[500px]">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400 text-[10px] sm:text-xs uppercase font-semibold">
                        <th class="py-3 px-4 sm:px-6">Fecha</th>
                        <th class="py-3 px-4 sm:px-6">{{ columnaFacultativo }}</th>
                        <th class="py-3 px-4 sm:px-6">Modalidad</th>
                        <th class="py-3 px-4 sm:px-6">Origen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800 text-xs sm:text-sm">
                    <tr v-for="item in guardiasFiltradas" :key="item.id" class="hover:bg-zinc-800/20">
                        <td class="py-2.5 px-4 sm:px-6 font-mono font-bold text-white whitespace-nowrap">
                            {{ item.fecha_formateada }} <span class="text-[10px] text-zinc-500 ml-1">({{ item.dia_semana }})</span>
                        </td>
                        <td class="py-2.5 px-4 sm:px-6 font-semibold flex items-center gap-2 text-zinc-200">
                            {{ item.facultativo }}
                            <Pin v-if="item.is_manual" class="w-3.5 h-3.5 text-amber-400" title="Fijada manualmente" />
                        </td>
                        <td class="py-2.5 px-4 sm:px-6">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-mono border whitespace-nowrap"
                                :class="item.es_festivo_oficial
                                    ? 'bg-rose-500/10 text-rose-400 border-rose-500/30'
                                    : (item.es_finde ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-zinc-800 text-zinc-400 border-zinc-700')"
                            >
                                {{ item.tipo_badge }}
                            </span>
                        </td>
                        <td class="py-2.5 px-4 sm:px-6 text-xs text-zinc-500 font-mono">
                            {{ item.observaciones || (item.is_manual ? 'Manual' : 'IA') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-center p-8 sm:p-12 bg-zinc-900/50 border border-dashed border-zinc-800 rounded-xl">
            <p class="text-sm text-zinc-500">No hay guardias registradas para el filtro seleccionado. Ejecuta la IA o haz clic en la cuadrícula.</p>
        </div>
    </div>
</template>
