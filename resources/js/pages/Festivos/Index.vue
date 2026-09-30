<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { PartyPopper, Trash2, CalendarDays, ShieldAlert, X } from '@lucide/vue';

const props = defineProps({
    festivosAgrupados: Object,
    permisos: Object
});

const form = useForm({
    fecha: '',
    descripcion: ''
});

const guardarFestivo = () => {
    form.post('/festivos', {
        preserveScroll: true,
        onSuccess: () => form.reset()
    });
};

const borrarFestivo = (id) => {
    if (confirm('¿Eliminar este festivo? Afectará a los próximos cálculos de IA.')) {
        router.delete(`/festivos/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Festivos del Servicio" />

    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- HEADER -->
        <div class="bg-gradient-to-r from-zinc-900 to-rose-950/40 border border-zinc-800 rounded-2xl p-6 shadow-xl flex items-center justify-between">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-mono font-bold">
                    <PartyPopper class="w-3.5 h-3.5" /> CONFIGURACIÓN GLOBAL
                </div>
                <h2 class="text-xl font-bold text-white tracking-tight">Festivos del Servicio</h2>
                <p class="text-sm text-zinc-400">Los días registrados aquí serán tratados automáticamente como turnos de 24 horas por la Inteligencia Artificial, tanto para Adjuntos como para Residentes.</p>
            </div>
        </div>

        <!-- ALERTA DE ERRORES -->
        <div v-if="$page.props.errors.festivos" class="p-4 bg-rose-500/20 border-2 border-rose-500/50 rounded-xl flex items-center gap-3 text-rose-200">
            <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
            <div>
                <span class="font-bold text-rose-400 uppercase text-xs tracking-wider block">Error</span>
                <p class="text-sm font-medium mt-0.5">{{ $page.props.errors.festivos }}</p>
            </div>
            <button @click="$page.props.errors.festivos = null" class="ml-auto p-1 hover:bg-rose-500/20 rounded-lg text-rose-400">
                <X class="w-5 h-5"/>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- FORMULARIO DE INGRESO -->
            <div v-if="permisos.es_admin" class="lg:col-span-1">
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 sticky top-6">
                    <h3 class="font-bold text-white mb-4 flex items-center gap-2">
                        <CalendarDays class="w-5 h-5 text-rose-400" /> Nuevo Festivo
                    </h3>
                    
                    <form @submit.prevent="guardarFestivo" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Fecha Exacta</label>
                            <input v-model="form.fecha" type="date" required class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-3 font-mono outline-none focus:border-rose-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Descripción (Opcional)</label>
                            <input v-model="form.descripcion" type="text" placeholder="Ej: Patrón del Hospital..." class="w-full bg-zinc-950 text-white rounded-lg border-zinc-800 p-3 outline-none focus:border-rose-500 transition-colors" />
                        </div>
                        <button type="submit" :disabled="form.processing" class="w-full py-3 bg-rose-500 hover:bg-rose-400 text-white font-bold rounded-lg transition-colors cursor-pointer mt-2 disabled:opacity-50 shadow-lg shadow-rose-500/20">
                            Añadir al Calendario
                        </button>
                    </form>
                </div>
            </div>

            <!-- LISTADO DE FESTIVOS AGRUPADOS POR AÑO -->
            <div :class="permisos.es_admin ? 'lg:col-span-2' : 'lg:col-span-3'" class="space-y-6">
                <div v-if="Object.keys(festivosAgrupados).length === 0" class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl p-12 text-center">
                    <PartyPopper class="w-12 h-12 text-zinc-700 mx-auto mb-3" />
                    <h3 class="text-lg font-bold text-zinc-400">Sin festivos registrados</h3>
                    <p class="text-sm text-zinc-500 mt-1">El calendario base asume únicamente los fines de semana.</p>
                </div>

                <div v-for="(festivos, anio) in festivosAgrupados" :key="anio" class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                    <div class="bg-zinc-950/50 px-6 py-4 border-b border-zinc-800 flex items-center justify-between">
                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                            Año {{ anio }}
                        </h3>
                        <span class="px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 text-xs font-bold">{{ festivos.length }} días</span>
                    </div>
                    
                    <div class="divide-y divide-zinc-800/60 p-2">
                        <div v-for="fest in festivos" :key="fest.id" class="flex justify-between items-center p-3 sm:p-4 hover:bg-zinc-800/30 rounded-lg transition-colors group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg bg-rose-500/10 border border-rose-500/20 flex flex-col items-center justify-center shrink-0">
                                    <span class="text-[10px] font-bold text-rose-400 uppercase leading-none">{{ fest.mes.substring(0,3) }}</span>
                                    <span class="text-lg font-black text-rose-300 leading-tight mt-0.5">{{ fest.fecha_formateada.split('/')[0] }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ fest.descripcion || 'Festivo Oficial' }}</p>
                                    <p class="text-xs font-mono text-zinc-500">{{ fest.fecha_formateada }}</p>
                                </div>
                            </div>
                            <button v-if="permisos.es_admin" @click="borrarFestivo(fest.id)" class="text-zinc-600 hover:text-rose-400 hover:bg-rose-500/10 p-2 rounded-lg transition-all opacity-0 group-hover:opacity-100 cursor-pointer">
                                <Trash2 class="w-5 h-5"/>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>