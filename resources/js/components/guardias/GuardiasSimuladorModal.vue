<script setup lang="ts">
import type { SimulacionResultado } from '@/composables/useGuardiasCalendar';
import {
    AlertTriangle,
    BarChart3,
    CalendarCheck2,
    CheckCircle2,
    Eye,
    Pin,
    ShieldAlert,
    Sparkles,
    X,
} from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
    abierto: boolean;
    variante: 'adjuntos' | 'residentes';
    resultado: SimulacionResultado | null;
    procesandoGuardado: boolean;
}>();

const emit = defineEmits<{
    (e: 'cerrar'): void;
    (e: 'aplicarCuadrante'): void;
}>();

const tabVistaPrevia = ref<'equidad' | 'turnos' | 'alertas'>('equidad');
</script>

<template>
    <div
        v-if="abierto && resultado"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/80 backdrop-blur-sm animate-in fade-in"
    >
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-5xl w-full max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <!-- CABECERA -->
            <div class="p-4 sm:p-5 bg-zinc-950 border-b border-zinc-800 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="p-2.5 rounded-xl border"
                        :class="variante === 'residentes'
                            ? 'bg-purple-500/10 border-purple-500/30 text-purple-400'
                            : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'"
                    >
                        <Eye class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-white text-sm sm:text-base">
                                Simulador Dry-Run — Previsualización del Cuadrante
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                                Sin cambios en BD
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            Revisa la equidad resultante y las alertas de topes antes de confirmar el guardado definitivo.
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('cerrar')"
                    class="text-zinc-400 hover:text-white p-1.5 rounded-lg hover:bg-zinc-800 cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- CONTENIDO SCROLLABLE -->
            <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5">
                <!-- ERROR DE COLAPSO SI OCURRIÓ -->
                <div
                    v-if="!resultado.ok && resultado.error"
                    class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/40 flex items-start gap-3 text-rose-200"
                >
                    <ShieldAlert class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold text-xs uppercase tracking-wider text-rose-400 block">
                            Simulación detenida por colapso de restricciones
                        </span>
                        <p class="text-xs sm:text-sm mt-1">{{ resultado.error }}</p>
                    </div>
                </div>

                <!-- TARJETAS RESUMEN -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Turnos Generados IA</span>
                            <span class="text-xl font-black font-mono text-white">{{ resultado.resumen.turnos_nuevos }}</span>
                        </div>
                        <CalendarCheck2 class="w-5 h-5 text-emerald-400" />
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Manuales Respetados</span>
                            <span class="text-xl font-black font-mono text-amber-400">{{ resultado.resumen.manuales_respetados }}</span>
                        </div>
                        <Pin class="w-5 h-5 text-amber-400" />
                    </div>
                    <div
                        class="p-3.5 rounded-xl bg-zinc-950 border flex items-center justify-between"
                        :class="resultado.alertas_relajacion.length > 0 ? 'border-amber-500/40' : 'border-zinc-800'"
                    >
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Topes Relajados</span>
                            <span
                                class="text-xl font-black font-mono"
                                :class="resultado.alertas_relajacion.length > 0 ? 'text-amber-400' : 'text-emerald-400'"
                            >
                                {{ resultado.alertas_relajacion.length }}
                            </span>
                        </div>
                        <AlertTriangle v-if="resultado.alertas_relajacion.length > 0" class="w-5 h-5 text-amber-400" />
                        <CheckCircle2 v-else class="w-5 h-5 text-emerald-400" />
                    </div>
                    <div
                        class="p-3.5 rounded-xl bg-zinc-950 border flex items-center justify-between"
                        :class="(resultado.alertas_tope_legal?.length ?? 0) > 0 ? 'border-indigo-500/40' : 'border-zinc-800'"
                    >
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Avisos >48h/sem (Info)</span>
                            <span
                                class="text-xl font-black font-mono"
                                :class="(resultado.alertas_tope_legal?.length ?? 0) > 0 ? 'text-indigo-300' : 'text-emerald-400'"
                            >
                                {{ resultado.alertas_tope_legal?.length ?? 0 }}
                            </span>
                        </div>
                        <AlertTriangle v-if="(resultado.alertas_tope_legal?.length ?? 0) > 0" class="w-5 h-5 text-indigo-400" />
                        <CheckCircle2 v-else class="w-5 h-5 text-emerald-400" />
                    </div>
                </div>

                <!-- PESTAÑAS INTERNAS DEL SIMULADOR -->
                <div class="flex gap-2 border-b border-zinc-800 pb-px">
                    <button
                        type="button"
                        @click="tabVistaPrevia = 'equidad'"
                        class="pb-2.5 px-3 text-xs font-bold border-b-2 flex items-center gap-1.5 cursor-pointer"
                        :class="tabVistaPrevia === 'equidad'
                            ? 'border-emerald-500 text-emerald-400'
                            : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                    >
                        <BarChart3 class="w-3.5 h-3.5" /> Equidad Simulada ({{ resultado.equidad_simulada.length }})
                    </button>
                    <button
                        type="button"
                        @click="tabVistaPrevia = 'turnos'"
                        class="pb-2.5 px-3 text-xs font-bold border-b-2 flex items-center gap-1.5 cursor-pointer"
                        :class="tabVistaPrevia === 'turnos'
                            ? 'border-emerald-500 text-emerald-400'
                            : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                    >
                        <CalendarCheck2 class="w-3.5 h-3.5" /> Listado Propuesto ({{ resultado.guardias_simuladas.length }})
                    </button>
                    <button
                        type="button"
                        @click="tabVistaPrevia = 'alertas'"
                        class="pb-2.5 px-3 text-xs font-bold border-b-2 flex items-center gap-1.5 cursor-pointer"
                        :class="tabVistaPrevia === 'alertas'
                            ? 'border-amber-500 text-amber-400'
                            : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                    >
                        <AlertTriangle class="w-3.5 h-3.5" /> Alertas y Avisos ({{ resultado.alertas_relajacion.length + (resultado.alertas_tope_legal?.length ?? 0) }})
                    </button>
                </div>

                <!-- TAB 1: EQUIDAD SIMULADA -->
                <div v-if="tabVistaPrevia === 'equidad'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="eq in resultado.equidad_simulada"
                        :key="eq.id"
                        class="bg-zinc-950 p-3 rounded-xl border border-zinc-800 font-mono"
                    >
                        <div class="flex justify-between items-center gap-1">
                            <span class="text-zinc-200 font-bold truncate text-xs">{{ eq.nombre }}</span>
                            <div class="flex items-center gap-1 shrink-0">
                                <span
                                    v-if="eq.porcentaje_jornada && eq.porcentaje_jornada < 100"
                                    class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30"
                                >
                                    {{ eq.porcentaje_jornada }}%
                                </span>
                                <span
                                    v-if="eq.excede_tope_legal"
                                    class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30"
                                    title="Supera tope semanal de 48h"
                                >
                                    >48h/sem
                                </span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
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
                            <div class="bg-zinc-900/60 p-1 rounded">
                                <span class="block text-rose-500/80 text-[8px]">Fest (Año)</span>
                                <b class="text-rose-400">{{ eq.festivos }} ({{ eq.festivos_anual }})</b>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LISTADO DE TURNOS SIMULADOS -->
                <div v-if="tabVistaPrevia === 'turnos'" class="border border-zinc-800 rounded-xl overflow-x-auto max-h-80">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-zinc-950 text-zinc-400 uppercase text-[10px] sticky top-0">
                            <tr class="border-b border-zinc-800">
                                <th class="py-2.5 px-4">Fecha</th>
                                <th class="py-2.5 px-4">Facultativo</th>
                                <th class="py-2.5 px-4">Modalidad</th>
                                <th class="py-2.5 px-4">Origen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/70">
                            <tr v-for="(item, idx) in resultado.guardias_simuladas" :key="idx" class="hover:bg-zinc-800/20">
                                <td class="py-2 px-4 font-mono font-bold text-white whitespace-nowrap">
                                    {{ item.fecha_formateada }} <span class="text-[10px] text-zinc-500">({{ item.dia_semana }})</span>
                                </td>
                                <td class="py-2 px-4 font-semibold text-zinc-200">
                                    {{ item.facultativo }}
                                </td>
                                <td class="py-2 px-4">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-mono border"
                                        :class="item.es_festivo_oficial
                                            ? 'bg-rose-500/10 text-rose-400 border-rose-500/30'
                                            : (item.es_finde ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-zinc-800 text-zinc-300 border-zinc-700')"
                                    >
                                        {{ item.tipo_badge }}
                                    </span>
                                </td>
                                <td class="py-2 px-4 font-mono text-zinc-400">
                                    {{ item.observaciones }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TAB 3: ALERTAS DE RELAJACIÓN Y AVISOS INFORMATIVOS 48H/SEM (F6-03) -->
                <div v-if="tabVistaPrevia === 'alertas'" class="space-y-3">
                    <div
                        v-if="resultado.alertas_relajacion.length === 0 && (resultado.alertas_tope_legal?.length ?? 0) === 0"
                        class="p-6 text-center bg-zinc-950/60 border border-zinc-800 rounded-xl text-xs text-emerald-400 flex flex-col items-center gap-2"
                    >
                        <CheckCircle2 class="w-6 h-6" />
                        <span>Todos los turnos respetaron los topes máximos mensuales y ninguna semana supera las 48h.</span>
                    </div>

                    <div v-if="(resultado.alertas_tope_legal?.length ?? 0) > 0" class="space-y-2">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-300 flex items-center gap-1.5">
                            <AlertTriangle class="w-3.5 h-3.5 text-indigo-400" />
                            Aviso Informativo — Semanas ISO con más de 48h de guardia (No bloqueante)
                        </div>
                        <div
                            v-for="(alertaLegal, idxL) in resultado.alertas_tope_legal"
                            :key="'legal-' + idxL"
                            class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-center gap-2.5">
                                <AlertTriangle class="w-4 h-4 text-indigo-400 shrink-0" />
                                <div>
                                    <span class="font-bold text-white">{{ alertaLegal.facultativo }}</span>
                                    <span class="text-zinc-300 ml-1">— {{ alertaLegal.motivo }}</span>
                                </div>
                            </div>
                            <span class="font-mono text-[11px] text-indigo-300 shrink-0">{{ alertaLegal.semana }} ({{ alertaLegal.horas_semana }}h)</span>
                        </div>
                    </div>

                    <div v-if="resultado.alertas_relajacion.length > 0" class="space-y-2">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-amber-300 flex items-center gap-1.5">
                            <AlertTriangle class="w-3.5 h-3.5 text-amber-400" />
                            Relajación de Topes Mensuales Configurados
                        </div>
                        <div
                            v-for="(alerta, i) in resultado.alertas_relajacion"
                            :key="'rel-' + i"
                            class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-center gap-2.5">
                                <AlertTriangle class="w-4 h-4 text-amber-400 shrink-0" />
                                <div>
                                    <span class="font-bold text-white">{{ alerta.facultativo }}</span>
                                    <span class="text-zinc-300 ml-1">— {{ alerta.motivo }}</span>
                                </div>
                            </div>
                            <span class="font-mono text-[11px] text-amber-300 shrink-0">{{ alerta.fecha_formateada }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PIE CON ACCIONES -->
            <div class="p-4 bg-zinc-950 border-t border-zinc-800 flex flex-col sm:flex-row justify-end gap-2.5">
                <button
                    type="button"
                    @click="emit('cerrar')"
                    class="px-4 py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold text-xs cursor-pointer transition-colors"
                >
                    Descartar Vista Previa
                </button>
                <button
                    v-if="resultado.ok"
                    type="button"
                    :disabled="procesandoGuardado"
                    @click="emit('aplicarCuadrante')"
                    class="px-5 py-2.5 rounded-xl font-black text-xs text-zinc-950 flex items-center justify-center gap-2 cursor-pointer transition-all disabled:opacity-50"
                    :class="variante === 'residentes' ? 'bg-purple-500 hover:bg-purple-400' : 'bg-emerald-500 hover:bg-emerald-400'"
                >
                    <Sparkles class="w-4 h-4 fill-zinc-950" />
                    {{ procesandoGuardado ? 'Guardando Cuadrante...' : 'Confirmar y Guardar Cuadrante' }}
                </button>
            </div>
        </div>
    </div>
</template>
