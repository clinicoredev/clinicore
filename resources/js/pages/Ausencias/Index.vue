<script setup>
import { ref } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { 
    CalendarPlus, Calendar, Clock, CheckCircle2, 
    XCircle, AlertCircle, FileText, User, X, ShieldAlert 
} from '@lucide/vue';

const props = defineProps({
    ausencias: Array,
    medicos: Array, // NUEVO: Recibimos la lista de facultativos
    permisos: Object
});

const page = usePage();

// Modal de solicitud
const modalAbierto = ref(false);

// Calculamos la fecha de hoy en formato YYYY-MM-DD para ponerla por defecto
const hoy = new Date().toISOString().split('T')[0];

const form = useForm({
    user_id: props.permisos.es_jefe && props.medicos.length > 0 ? props.medicos[0].id : page.props.auth.user.id, // NUEVO: Preselecciona un médico si es jefe
    tipo: 'congreso',
    fecha_inicio: hoy,
    fecha_fin: hoy,
    motivo: 'Ponente principal en mesa redonda de actualización clínica.'
});

const enviarSolicitud = () => {
    form.post('/ausencias', {
        onSuccess: () => {
            modalAbierto.value = false;
            form.reset('motivo'); // Solo reseteamos el motivo por comodidad
        }
    });
};

// Acción exclusiva del Jefe de Servicio (Verbo PATCH)
const resolverPeticion = (id, nuevoEstado) => {
    router.patch(`/ausencias/${id}/resolver`, {
        estado: nuevoEstado
    }, {
        preserveScroll: true // Evita que la pantalla pegue un salto arriba al hacer clic
    });
};
</script>

<template>
    <Head title="Permisos y Ausencias" />

    <div class="space-y-6 relative">
        
        <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    Tablón de Solicitudes del Departamento
                    <span v-if="permisos.es_jefe" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 font-bold">
                        Modo Revisor
                    </span>
                </h2>
                <p class="text-sm text-zinc-400 mt-1">
                    {{ permisos.es_jefe ? 'Gestiona y autoriza las ausencias de tu equipo médico.' : 'Consulta el estado de tus solicitudes enviadas a jefatura.' }}
                </p>
            </div>

            <button 
                @click="modalAbierto = true"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold text-sm transition-all shadow-lg shadow-emerald-500/10 cursor-pointer active:scale-95 shrink-0"
            >
                <CalendarPlus class="w-4 h-4" />
                Nueva Solicitud
            </button>
        </div>

        <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            
            <div v-if="ausencias.length === 0" class="p-12 text-center flex flex-col items-center justify-center space-y-3">
                <div class="p-3 rounded-full bg-zinc-800/60 text-zinc-500">
                    <Calendar class="w-8 h-8" />
                </div>
                <div class="text-zinc-300 font-medium">No hay ninguna solicitud registrada</div>
                <p class="text-xs text-zinc-500 max-w-sm">
                    Las peticiones de vacaciones, congresos o bajas médicas que realices aparecerán listadas en este panel.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400 text-xs uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-6">Tipo / Motivo</th>
                            <th class="py-3.5 px-6">Facultativo</th>
                            <th class="py-3.5 px-6">Fechas</th>
                            <th class="py-3.5 px-6">Estado</th>
                            <th class="py-3.5 px-6 text-right">Resolución</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-sm">
                        
                        <tr v-for="item in ausencias" :key="item.id" class="hover:bg-zinc-800/20 transition-colors">
                            
                            <td class="py-4 px-6 max-w-xs">
                                <div class="font-semibold text-white flex items-center gap-2">
                                    <FileText class="w-4 h-4 text-emerald-400 shrink-0" />
                                    {{ item.tipo }}
                                </div>
                                <div class="text-xs text-zinc-400 mt-1 truncate" :title="item.motivo">
                                    "{{ item.motivo }}"
                                </div>
                            </td>

                            <td class="py-4 px-6 font-medium text-zinc-200">
                                <div class="flex items-center gap-1.5">
                                    <User class="w-3.5 h-3.5 text-zinc-500" />
                                    {{ item.solicitante }}
                                </div>
                            </td>

                            <td class="py-4 px-6 text-zinc-300 text-xs font-mono">
                                <div>{{ item.fechas }}</div>
                                <span class="inline-block mt-1 text-[10px] font-sans px-1.5 py-0.2 bg-zinc-800 text-zinc-400 rounded border border-zinc-700">
                                    {{ item.dias_totales }} días
                                </span>
                            </td>

                            <td class="py-4 px-6">
                                <span 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border"
                                    :class="{
                                        'bg-amber-500/10 text-amber-400 border-amber-500/20 animate-pulse': item.estado === 'pendiente',
                                        'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': item.estado === 'aprobada',
                                        'bg-rose-500/10 text-rose-400 border-rose-500/20': item.estado === 'denegada'
                                    }"
                                >
                                    <Clock v-if="item.estado === 'pendiente'" class="w-3 h-3" />
                                    <CheckCircle2 v-else-if="item.estado === 'aprobada'" class="w-3 h-3" />
                                    <XCircle v-else class="w-3 h-3" />
                                    <span class="capitalize">{{ item.estado }}</span>
                                </span>
                            </td>

                            <td class="py-4 px-6 text-right">
                                
                                <div v-if="permisos.es_jefe && item.estado === 'pendiente'" class="inline-flex items-center gap-2">
                                    <button 
                                        @click="resolverPeticion(item.id, 'aprobada')"
                                        title="Autorizar permiso"
                                        class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-zinc-950 transition-all border border-emerald-500/30 cursor-pointer"
                                    >
                                        <CheckCircle2 class="w-4 h-4" />
                                    </button>

                                    <button 
                                        @click="resolverPeticion(item.id, 'denegada')"
                                        title="Denegar permiso"
                                        class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all border border-rose-500/30 cursor-pointer"
                                    >
                                        <XCircle class="w-4 h-4" />
                                    </button>
                                </div>

                                <div v-else-if="item.estado !== 'pendiente'" class="text-[11px] text-zinc-500">
                                    Firmado por:<br>
                                    <span class="text-zinc-400 font-medium">{{ item.revisor }}</span>
                                </div>

                                <span v-else class="text-xs text-zinc-600 italic">En revisión...</span>

                            </td>

                        </tr>

                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4">
            <div class="w-full max-w-lg rounded-xl bg-zinc-900 border border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                
                <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-4 bg-zinc-950/50">
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <CalendarPlus class="w-4 h-4 text-emerald-400" />
                        Tramitar Ausencia / Congreso
                    </h3>
                    <button @click="modalAbierto = false" class="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-800">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="enviarSolicitud" class="p-6 space-y-4">
                    
                    <!-- NUEVO: Selector de Facultativo (Solo visible para Jefes) -->
                    <div v-if="permisos.es_jefe">
                        <label class="block text-xs font-medium text-amber-400 uppercase mb-1">Registrar en nombre de (Facultativo)</label>
                        <select v-model="form.user_id" required class="w-full rounded-lg bg-amber-950/20 border border-amber-500/30 px-3.5 py-2 text-sm text-white focus:outline-hidden focus:border-amber-500">
                            <option v-for="medico in medicos" :key="medico.id" :value="medico.id">
                                {{ medico.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-400 uppercase mb-1">Tipo de Permiso</label>
                        <select v-model="form.tipo" class="w-full rounded-lg bg-zinc-950 border border-zinc-800 px-3.5 py-2 text-sm text-white focus:outline-hidden focus:border-emerald-500">
                            <option value="congreso">Asistencia a Congreso / Actividad Científica</option>
                            <option value="vacaciones">Vacaciones ordinarias</option>
                            <option value="asuntos_propios">Día de asuntos propios</option>
                            <option value="baja_medica">Incapacidad Temporal / Baja médica</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 uppercase mb-1">Primer día ausente</label>
                            <input v-model="form.fecha_inicio" type="date" required class="w-full rounded-lg bg-zinc-950 border border-zinc-800 px-3 py-2 text-sm text-white font-mono focus:outline-hidden focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 uppercase mb-1">Último día ausente</label>
                            <input v-model="form.fecha_fin" type="date" required class="w-full rounded-lg bg-zinc-950 border border-zinc-800 px-3 py-2 text-sm text-white font-mono focus:outline-hidden focus:border-emerald-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-400 uppercase mb-1">Justificación / Detalles</label>
                        <textarea v-model="form.motivo" rows="3" placeholder="Indica el nombre del congreso o notas para jefatura..." class="w-full rounded-lg bg-zinc-950 border border-zinc-800 p-3 text-sm text-white focus:outline-hidden focus:border-emerald-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800">
                        <button @click="modalAbierto = false" type="button" class="px-4 py-2 rounded-lg text-sm text-zinc-400 hover:text-white hover:bg-zinc-800 cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold text-sm cursor-pointer disabled:opacity-50">
                            {{ form.processing ? 'Enviando...' : 'Registrar Solicitud' }}
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</template>