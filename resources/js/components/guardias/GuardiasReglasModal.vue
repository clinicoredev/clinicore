<script setup lang="ts">
import type { LimitacionItem, MedicoItem } from '@/composables/useGuardiasCalendar';
import { Ban, CalendarDays, Trash2, User } from '@lucide/vue';

defineProps<{
    variante: 'adjuntos' | 'residentes';
    esJefe: boolean;
    medicos: MedicoItem[];
    formLimitacion: Record<string, any>;
    limitacionesRecurrentes: LimitacionItem[];
    limitacionesMensuales: LimitacionItem[];
    tituloFormulario: string;
    labelSujetoImplicado: string;
    placeholderMotivo: string;
}>();

const emit = defineEmits<{
    (e: 'guardarRegla'): void;
    (e: 'eliminarLimitacion', id: number): void;
}>();
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 animate-in fade-in duration-200">
        <div v-if="esJefe" class="p-4 sm:p-6 bg-zinc-900 border border-zinc-800 rounded-xl h-fit">
            <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2 mb-4">
                <Ban class="w-4 h-4 text-rose-400" /> {{ tituloFormulario }}
            </h3>
            <form @submit.prevent="emit('guardarRegla')" class="space-y-4 text-xs">
                <div>
                    <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">{{ labelSujetoImplicado }}</label>
                    <select
                        v-model="formLimitacion.user_id"
                        class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 outline-none"
                        :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                    >
                        <option class="bg-zinc-900" v-for="m in medicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">Tipo de Regla</label>
                    <select
                        v-model="formLimitacion.tipo"
                        class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 outline-none"
                        :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                    >
                        <option class="bg-zinc-900" value="dia_semana">Día semana (recurrente)</option>
                        <option class="bg-zinc-900" value="fecha_concreta">Día exacto</option>
                        <option class="bg-zinc-900" value="periodo">Rango de días</option>
                    </select>
                </div>

                <div v-if="formLimitacion.tipo === 'dia_semana'">
                    <select
                        v-model="formLimitacion.valor"
                        class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 outline-none"
                        :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                    >
                        <option class="bg-zinc-900" value="1">Lunes</option>
                        <option class="bg-zinc-900" value="2">Martes</option>
                        <option class="bg-zinc-900" value="3">Miércoles</option>
                        <option class="bg-zinc-900" value="4">Jueves</option>
                        <option class="bg-zinc-900" value="5">Viernes</option>
                        <option class="bg-zinc-900" value="6">Sábado</option>
                        <option class="bg-zinc-900" value="7">Domingo</option>
                    </select>
                </div>

                <div v-else-if="formLimitacion.tipo === 'fecha_concreta'">
                    <input
                        v-model="formLimitacion.valor"
                        type="date"
                        required
                        class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 font-mono outline-none"
                        :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                    />
                </div>

                <div v-else-if="formLimitacion.tipo === 'periodo'" class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="block text-[9px] text-zinc-500 mb-0.5">Desde</span>
                        <input
                            v-model="formLimitacion.fecha_inicio"
                            type="date"
                            required
                            class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 font-mono outline-none"
                            :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                        />
                    </div>
                    <div>
                        <span class="block text-[9px] text-zinc-500 mb-0.5">Hasta</span>
                        <input
                            v-model="formLimitacion.fecha_fin"
                            type="date"
                            required
                            class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 font-mono outline-none"
                            :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] text-zinc-500 uppercase mb-1 font-bold">Justificación (Opcional)</label>
                    <input
                        v-model="formLimitacion.motivo"
                        type="text"
                        :placeholder="placeholderMotivo"
                        class="w-full bg-zinc-950 text-white rounded-lg border border-zinc-800 p-2.5 outline-none"
                        :class="variante === 'residentes' ? 'focus:border-purple-500' : 'focus:border-emerald-500'"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="formLimitacion.processing"
                    class="w-full py-2.5 text-zinc-950 font-bold rounded-lg transition-colors cursor-pointer mt-2 disabled:opacity-50"
                    :class="variante === 'residentes' ? 'bg-purple-500 hover:bg-purple-400' : 'bg-emerald-500 hover:bg-emerald-400'"
                >
                    Registrar Regla
                </button>
            </form>
        </div>

        <div :class="esJefe ? 'lg:col-span-2' : 'lg:col-span-3'" class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 sm:p-6 space-y-8">
            <div>
                <h3 class="font-bold text-white mb-4 text-sm sm:text-base flex items-center gap-2">
                    <User class="w-4 h-4" :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'" />
                    Patrones Recurrentes Semanales
                </h3>
                <div v-if="limitacionesRecurrentes.length === 0" class="text-xs text-zinc-500 italic p-4 text-center border border-dashed border-zinc-800 rounded-lg">
                    No hay reglas recurrentes activas.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="regla in limitacionesRecurrentes"
                        :key="regla.id"
                        class="p-3 bg-zinc-950 rounded-lg border border-zinc-800 flex flex-col sm:flex-row justify-between sm:items-center text-xs sm:text-sm gap-2"
                    >
                        <div>
                            <span class="font-bold" :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'">
                                {{ regla.medico }}
                            </span>
                            <span class="text-zinc-400 ml-1">
                                prohibido: <u class="text-zinc-200 font-mono bg-zinc-900 px-1 py-0.5 rounded">{{ regla.regla }}</u>
                            </span>
                            <span v-if="regla.motivo" class="block text-[10px] text-zinc-500 mt-1 italic">"{{ regla.motivo }}"</span>
                        </div>
                        <button
                            v-if="esJefe"
                            @click="emit('eliminarLimitacion', regla.id)"
                            class="text-rose-400 bg-rose-500/10 p-1.5 rounded self-end sm:self-auto cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-bold text-white mb-4 text-sm sm:text-base flex items-center gap-2">
                    <CalendarDays class="w-4 h-4 text-amber-400" /> Días Exactos y Periodos Bloqueados
                </h3>
                <div v-if="limitacionesMensuales.length === 0" class="text-xs text-zinc-500 italic p-4 text-center border border-dashed border-zinc-800 rounded-lg">
                    Ninguna restricción puntual o de rango registrada.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="regla in limitacionesMensuales"
                        :key="regla.id"
                        class="p-3 bg-zinc-950 rounded-lg border border-zinc-800 flex flex-col sm:flex-row justify-between sm:items-center text-xs sm:text-sm gap-2"
                    >
                        <div>
                            <span class="font-bold" :class="variante === 'residentes' ? 'text-purple-400' : 'text-emerald-400'">
                                {{ regla.medico }}
                            </span>
                            <span class="text-zinc-400 ml-1">
                                prohibido: <u class="text-zinc-200 font-mono bg-zinc-900 px-1 py-0.5 rounded">{{ regla.regla }}</u>
                            </span>
                            <span v-if="regla.motivo" class="block text-[10px] text-zinc-500 mt-1 italic">"{{ regla.motivo }}"</span>
                        </div>
                        <button
                            v-if="esJefe"
                            @click="emit('eliminarLimitacion', regla.id)"
                            class="text-rose-400 bg-rose-500/10 p-1.5 rounded self-end sm:self-auto cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
