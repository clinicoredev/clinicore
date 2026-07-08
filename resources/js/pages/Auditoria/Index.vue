<script setup>
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, PlusCircle, Pencil, Trash2, Clock } from '@lucide/vue';

defineProps({
    logs: Object
});

// Función para elegir el icono y color según el evento
const obtenerIcono = (evento) => {
    switch(evento) {
        case 'created': return { icon: PlusCircle, color: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' };
        case 'updated': return { icon: Pencil, color: 'text-amber-400 bg-amber-500/10 border-amber-500/20' };
        case 'deleted': return { icon: Trash2, color: 'text-rose-400 bg-rose-500/10 border-rose-500/20' };
        default: return { icon: ShieldAlert, color: 'text-indigo-400 bg-indigo-500/10 border-indigo-500/20' };
    }
};
</script>

<template>
    <Head title="Registro de Auditoría" />

    <div class="max-w-4xl mx-auto space-y-6">
        
        <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-xl flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <ShieldAlert class="w-5 h-5 text-indigo-400" />
                    Registro de Auditoría y Trazabilidad
                </h2>
                <p class="text-sm text-zinc-400 mt-1">
                    Historial inmutable de modificaciones en el departamento.
                </p>
            </div>
        </div>

        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
            
            <div v-if="logs.data.length === 0" class="text-center py-12 text-zinc-500">
                <ShieldAlert class="w-10 h-10 mx-auto mb-3 opacity-50" />
                No hay registros de actividad recientes.
            </div>

            <!-- TIMELINE VERTICAL -->
            <div v-else class="relative border-l border-zinc-800 ml-4 space-y-8">
                <div v-for="log in logs.data" :key="log.id" class="relative pl-8">
                    
                    <!-- Icono flotante del evento -->
                    <div class="absolute -left-5 top-0.5 p-2 rounded-full border" :class="obtenerIcono(log.evento).color">
                        <component :is="obtenerIcono(log.evento).icon" class="w-4 h-4" />
                    </div>

                    <div class="bg-zinc-950 border border-zinc-800/60 rounded-lg p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                            <div class="text-sm font-semibold text-white">
                                {{ log.causante }}
                                <span class="text-zinc-500 font-normal ml-1">ha interactuado con</span> 
                                <span class="text-indigo-400">{{ log.modelo }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-zinc-500 font-mono">
                                <Clock class="w-3.5 h-3.5" />
                                {{ log.fecha }} <span class="hidden sm:inline">({{ log.hace_tiempo }})</span>
                            </div>
                        </div>

                        <p class="text-sm text-zinc-300">
                            {{ log.descripcion }}
                        </p>

                        <!-- Desplegable opcional con los datos exactos del cambio -->
                        <div v-if="Object.keys(log.propiedades).length > 0" class="mt-3 p-3 bg-zinc-900 rounded border border-zinc-800 overflow-x-auto">
                            <pre class="text-[10px] text-zinc-400 font-mono whitespace-pre-wrap">{{ JSON.stringify(log.propiedades, null, 2) }}</pre>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Paginación Simple -->
            <div class="mt-8 flex justify-between border-t border-zinc-800 pt-4" v-if="logs.next_page_url || logs.prev_page_url">
                <a :href="logs.prev_page_url" :class="{'pointer-events-none opacity-50': !logs.prev_page_url}" class="px-4 py-2 text-sm text-zinc-400 hover:text-white transition-colors">← Anteriores</a>
                <a :href="logs.next_page_url" :class="{'pointer-events-none opacity-50': !logs.next_page_url}" class="px-4 py-2 text-sm text-zinc-400 hover:text-white transition-colors">Siguientes →</a>
            </div>

        </div>
    </div>
</template>