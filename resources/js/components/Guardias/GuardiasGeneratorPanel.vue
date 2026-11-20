<script setup lang="ts">
import type { MedicoItem } from '@/composables/useGuardiasCalendar';
import {
    CheckCircle2,
    Cpu,
    Eye,
    GraduationCap,
    Moon,
    Settings2,
    Sliders,
    Sparkles,
    Trash2,
} from '@lucide/vue';

defineProps<{
    variante: 'adjuntos' | 'residentes';
    basePath: string;
    badgeMotor: string;
    tituloPlanificador: string;
    etiquetaPlantillaCompleta: string;
    etiquetaSelectorMedicos: string;
    tituloParametros: string;
    esJefe: boolean;
    medicos: MedicoItem[];
    mesFiltro: number;
    anioFiltro: number;
    formGenerador: Record<string, any>;
    simulando?: boolean;
}>();

const emit = defineEmits<{
    (e: 'vaciarMes'): void;
    (e: 'simularAlgoritmo'): void;
    (e: 'dispararAlgoritmo'): void;
}>();
</script>

<template>
    <div
        class="border border-zinc-800 rounded-xl sm:rounded-2xl shadow-xl overflow-hidden"
        :class="variante === 'residentes'
            ? 'bg-gradient-to-r from-zinc-900 via-zinc-900 to-purple-950/40'
            : 'bg-gradient-to-r from-zinc-900 via-zinc-900 to-emerald-950/40'"
    >
        <div class="p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 sm:gap-6">
            <div class="space-y-1">
                <div
                    class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-mono font-bold border"
                    :class="variante === 'residentes'
                        ? 'bg-purple-500/10 border-purple-500/30 text-purple-400'
                        : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'"
                >
                    <GraduationCap v-if="variante === 'residentes'" class="w-3.5 h-3.5" />
                    <Cpu v-else class="w-3.5 h-3.5 animate-spin" />
                    {{ badgeMotor }}
                </div>
                <h2 class="text-lg sm:text-xl font-bold text-white tracking-tight">{{ tituloPlanificador }}</h2>
            </div>

            <div v-if="esJefe" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 shrink-0">
                <button
                    @click="emit('vaciarMes')"
                    class="px-4 py-2.5 rounded-lg border border-rose-500/30 text-rose-400 hover:bg-rose-500/10 transition-all font-bold text-xs sm:text-sm flex justify-center items-center gap-2 cursor-pointer"
                >
                    <Trash2 class="w-4 h-4" /> Limpiar Mes
                </button>
                <button
                    type="button"
                    @click="emit('simularAlgoritmo')"
                    :disabled="formGenerador.processing || simulando"
                    class="px-4 py-2.5 rounded-lg border border-indigo-500/40 bg-indigo-500/10 text-indigo-300 hover:bg-indigo-500/20 font-bold text-xs sm:text-sm transition-all flex justify-center items-center gap-2 disabled:opacity-50 cursor-pointer"
                >
                    <Eye class="w-4 h-4" />
                    {{ simulando ? 'Simulando...' : 'Simular (Dry-Run)' }}
                </button>
                <button
                    @click="emit('dispararAlgoritmo')"
                    :disabled="formGenerador.processing || simulando"
                    class="px-5 py-2.5 rounded-lg text-zinc-950 font-bold text-xs sm:text-sm transition-all shadow-lg flex justify-center items-center gap-2 disabled:opacity-50 cursor-pointer"
                    :class="variante === 'residentes' ? 'bg-purple-500 hover:bg-purple-400' : 'bg-emerald-500 hover:bg-emerald-400'"
                >
                    <Sparkles class="w-4 h-4 fill-zinc-950" />
                    {{ formGenerador.processing ? 'Calculando...' : 'Generar IA' }}
                </button>
                <div class="flex items-center gap-2">
                    <a
                        :href="`${basePath}/exportar/excel?mes=${mesFiltro}&anio=${anioFiltro}`"
                        class="flex-1 text-center px-3 py-2 rounded-lg font-bold text-xs border"
                        :class="variante === 'residentes'
                            ? 'bg-purple-500/10 text-purple-400 border-purple-500/30'
                            : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'"
                    >
                        📊 Excel
                    </a>
                    <a
                        :href="`${basePath}/exportar/pdf?mes=${mesFiltro}&anio=${anioFiltro}`"
                        class="flex-1 text-center px-3 py-2 rounded-lg bg-rose-500/10 text-rose-400 font-bold text-xs border border-rose-500/30"
                    >
                        📄 PDF
                    </a>
                </div>
            </div>
        </div>

        <div v-if="esJefe" class="border-t border-zinc-800/80 bg-zinc-950/60 p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-zinc-800/60">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="formGenerador.usar_plantilla_completa"
                        class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 focus:ring-offset-zinc-950"
                        :class="variante === 'residentes' ? 'text-purple-500 focus:ring-purple-500' : 'text-emerald-500 focus:ring-emerald-500'"
                    />
                    <span class="text-sm font-semibold text-zinc-200 flex items-center gap-2">
                        <Settings2 class="w-4 h-4" :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'" />
                        {{ etiquetaPlantillaCompleta }}
                    </span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="formGenerador.proteger_vispera_vacaciones"
                        class="w-4 h-4 rounded border-zinc-700 bg-zinc-900"
                        :class="variante === 'residentes' ? 'text-purple-500 focus:ring-purple-500' : 'text-emerald-500 focus:ring-emerald-500'"
                    />
                    <span class="text-xs font-semibold text-zinc-300">Proteger víspera de vacaciones</span>
                </label>
            </div>

            <div v-if="!formGenerador.usar_plantilla_completa" class="pt-1 pb-3 border-b border-zinc-800/60 animate-in fade-in">
                <span class="block text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3">
                    {{ etiquetaSelectorMedicos }}
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                    <label
                        v-for="m in medicos"
                        :key="m.id"
                        class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                        :class="formGenerador.medicos_incluidos.includes(m.id)
                            ? (variante === 'residentes' ? 'bg-purple-500/10 border-purple-500/30 text-purple-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400')
                            : 'bg-zinc-900 border-zinc-800 text-zinc-500'"
                    >
                        <input type="checkbox" :value="m.id" v-model="formGenerador.medicos_incluidos" class="hidden" />
                        <CheckCircle2 class="w-4 h-4" :class="formGenerador.medicos_incluidos.includes(m.id) ? 'opacity-100' : 'opacity-0'" />
                        <span class="text-xs font-bold truncate">{{ m.name }}</span>
                    </label>
                </div>
            </div>

            <div>
                <span
                    class="block text-xs font-bold uppercase tracking-wider mb-3 flex items-center gap-2"
                    :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
                >
                    <Sliders class="w-3.5 h-3.5" /> {{ tituloParametros }}
                </span>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-8 gap-3">
                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                        <label class="block text-xs font-bold text-white mb-1">Simultáneos / Día</label>
                        <input
                            type="number"
                            min="1"
                            max="10"
                            v-model="formGenerador.personas_por_dia"
                            class="w-16 bg-zinc-950 border border-zinc-700 rounded px-2 py-1 text-xs font-bold font-mono outline-none"
                            :class="variante === 'residentes' ? 'text-purple-400 focus:border-purple-500' : 'text-emerald-400 focus:border-emerald-500'"
                        />
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2">
                        <label class="block text-[10px] font-bold text-white mb-1 uppercase tracking-wider">Modalidad</label>
                        <select
                            v-model="formGenerador.modalidad"
                            class="w-full bg-zinc-950 border border-zinc-700 rounded px-2 py-1.5 text-xs font-bold outline-none"
                            :class="variante === 'residentes' ? 'text-purple-400 focus:border-purple-500' : 'text-emerald-400 focus:border-emerald-500'"
                        >
                            <option class="bg-zinc-900" value="presencial">Presencial</option>
                            <option class="bg-zinc-900" value="localizada">Localizada</option>
                        </select>
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="formGenerador.respetar_salientes"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-950"
                                :class="variante === 'residentes' ? 'text-purple-500 focus:ring-purple-500' : 'text-emerald-500 focus:ring-emerald-500'"
                            />
                            <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                <Moon class="w-3.5 h-3.5 text-indigo-400" /> Salientes
                            </span>
                        </label>
                        <div v-if="formGenerador.respetar_salientes" class="text-[11px] text-zinc-400">
                            <select
                                v-model="formGenerador.distancia_minima_dias"
                                class="w-full bg-zinc-950 border border-zinc-700 rounded px-1.5 py-1 text-xs font-bold outline-none"
                                :class="variante === 'residentes' ? 'text-purple-400 focus:border-purple-500' : 'text-emerald-400 focus:border-emerald-500'"
                            >
                                <option class="bg-zinc-900" :value="0">0 días libres (Seguidos)</option>
                                <option class="bg-zinc-900" :value="1">1 día libre (Días alternos)</option>
                                <option class="bg-zinc-900" :value="2">2 días libres (Cadencia 72h)</option>
                                <option class="bg-zinc-900" :value="3">3 días libres en medio</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2 col-span-1 md:col-span-2 lg:col-span-2">
                        <label class="block text-[10px] font-bold text-white mb-1 uppercase tracking-wider">Agrupación de Fin de Semana</label>
                        <select
                            v-model="formGenerador.agrupacion"
                            class="w-full bg-zinc-950 border border-zinc-700 rounded px-2 py-1.5 text-xs font-bold outline-none"
                            :class="variante === 'residentes' ? 'text-purple-400 focus:border-purple-500' : 'text-emerald-400 focus:border-emerald-500'"
                        >
                            <option class="bg-zinc-900" value="ninguna">Sin agrupar (Días sueltos)</option>
                            <option class="bg-zinc-900" value="v_s">Viernes + Sábado (V+S)</option>
                            <option class="bg-zinc-900" value="s_d">Sábado + Domingo (S+D)</option>
                            <option class="bg-zinc-900" value="v_s_d">Pack Completo (V+S+D)</option>
                        </select>
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                        <label class="block text-[10px] font-bold text-white mb-1">Máx Totales (0=Sin tope)</label>
                        <input
                            type="number"
                            min="0"
                            max="15"
                            v-model="formGenerador.max_guardias_mes"
                            class="w-16 bg-zinc-950 border border-zinc-700 rounded px-2 py-1 text-xs font-bold font-mono outline-none"
                            :class="variante === 'residentes' ? 'text-purple-400 focus:border-purple-500' : 'text-emerald-400 focus:border-emerald-500'"
                        />
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-1">
                        <label class="block text-[10px] font-bold text-white mb-1">Topes por Tipo</label>
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="text-[10px] text-zinc-500">17h:</span>
                            <input
                                type="number"
                                min="0"
                                max="15"
                                v-model="formGenerador.max_diarias_mes"
                                class="w-12 bg-zinc-950 border border-zinc-700 rounded px-1.5 py-0.5 text-xs font-bold font-mono outline-none"
                                :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
                            />
                        </div>
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] text-zinc-500">24h:</span>
                            <input
                                type="number"
                                min="0"
                                max="10"
                                v-model="formGenerador.max_findes_mes"
                                class="w-12 bg-zinc-950 border border-zinc-700 rounded px-1.5 py-0.5 text-xs font-bold font-mono outline-none"
                                :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'"
                            />
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-xl space-y-2 col-span-1 md:col-span-2 lg:col-span-1">
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input
                                type="checkbox"
                                v-model="formGenerador.prorratear_ausencias"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-950"
                                :class="variante === 'residentes' ? 'text-purple-500 focus:ring-purple-500' : 'text-emerald-500 focus:ring-emerald-500'"
                            />
                            <span class="text-[10px] font-bold text-white leading-tight">Prorratear Bajas</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="formGenerador.usar_memoria_anual"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-950"
                                :class="variante === 'residentes' ? 'text-purple-500 focus:ring-purple-500' : 'text-emerald-500 focus:ring-emerald-500'"
                            />
                            <span class="text-[10px] font-bold text-white leading-tight">Memoria YTD</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
