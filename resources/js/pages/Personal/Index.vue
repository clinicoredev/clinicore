<script setup>
import { ref } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { 
    UserPlus, Shield, User, Mail, Calendar, X, 
    Pencil, Trash2, ShieldAlert, CheckCircle2 
} from '@lucide/vue';

const props = defineProps({
    miembros: Array,
    metricas_plan: Object,
    permisos: Object
});

const page = usePage();

// Modales independientes
const modalAltaAbierto = ref(false);
const modalEditarAbierto = ref(false);

const formAlta = useForm({
    name: '', email: '', rol: 'Facultativo'
});

const formEditar = useForm({
    id: null, name: '', email: '', rol: ''
});

const enviarAlta = () => {
    formAlta.post('/personal', {
        onSuccess: () => { modalAltaAbierto.value = false; formAlta.reset(); }
    });
};

const abrirEdicion = (medico) => {
    formEditar.id = medico.id;
    formEditar.name = medico.name;
    formEditar.email = medico.email;
    formEditar.rol = medico.rol;
    modalEditarAbierto.value = true;
};

const enviarEdicion = () => {
    formEditar.patch(`/personal/${formEditar.id}`, {
        onSuccess: () => { modalEditarAbierto.value = false; }
    });
};

const eliminarFacultativo = (id, nombre) => {
    if (confirm(`⚠️ ¿Estás completamente seguro de revocar el acceso corporativo y eliminar al ${nombre}?`)) {
        router.delete(`/personal/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Equipo Médico" />

    <div class="space-y-6 relative">
        
        <div v-if="page.props.errors?.suicide" class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 flex items-center gap-3 text-rose-200 animate-bounce">
            <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
            <div class="text-xs">
                <b class="uppercase tracking-wider text-rose-400 block">Acción Denegada</b>
                {{ page.props.errors.suicide }}
            </div>
        </div>

        <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <h2 class="text-base font-semibold text-white flex items-center gap-2">
                    Licencias del Servicio <span class="text-xs px-2 py-0.5 rounded bg-zinc-800 text-zinc-400 font-normal">Cardiología</span>
                </h2>
                <p class="text-sm text-zinc-400">
                    Has utilizado <span class="text-emerald-400 font-bold">{{ metricas_plan.total }}</span> de las {{ metricas_plan.maximo }} plazas disponibles.
                </p>
                <div class="w-full md:w-80 bg-zinc-950 h-2 rounded-full mt-3 overflow-hidden p-0.5 border border-zinc-800">
                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" :style="{ width: metricas_plan.porcentaje + '%' }"></div>
                </div>
            </div>

            <button v-if="permisos.es_jefe && metricas_plan.puede_invitar" @click="modalAltaAbierto = true" class="px-4 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-semibold text-sm transition-all cursor-pointer shrink-0 flex items-center gap-2">
                <UserPlus class="w-4 h-4" /> Dar de alta médico
            </button>
        </div>

        <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400 text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3.5 px-6">Facultativo</th>
                        <th class="py-3.5 px-6">Rol</th>
                        <th class="py-3.5 px-6">Alta</th>
                        <th class="py-3.5 px-6 text-center">Estado</th>
                        <th v-if="permisos.es_jefe" class="py-3.5 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-sm">
                    <tr v-for="miembro in miembros" :key="miembro.id" class="hover:bg-zinc-800/30 transition-colors group">
                        
                        <td class="py-4 px-6 font-medium text-white">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-300 uppercase">
                                    {{ miembro.name.slice(0, 2) }}
                                </div>
                                <div>
                                    <div class="text-zinc-100 font-semibold group-hover:text-emerald-400">{{ miembro.name }}</div>
                                    <div class="text-xs text-zinc-500 flex items-center gap-1 mt-0.5"><Mail class="w-3 h-3 text-zinc-600" />{{ miembro.email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border" :class="miembro.rol === 'Jefe de Servicio' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20'">
                                <Shield v-if="miembro.rol === 'Jefe de Servicio'" class="w-3 h-3" /><User v-else class="w-3 h-3" /> {{ miembro.rol }}
                            </span>
                        </td>

                        <td class="py-4 px-6 text-zinc-400 text-xs">{{ miembro.creado_el }}</td>

                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Activo
                            </span>
                        </td>

                        <td v-if="permisos.es_jefe" class="py-4 px-6 text-right">
                            <div class="inline-flex items-center gap-1">
                                <button @click="abrirEdicion(miembro)" title="Editar" class="p-2 text-zinc-400 hover:text-emerald-400 rounded-lg hover:bg-zinc-800 transition-colors cursor-pointer">
                                    <Pencil class="w-4 h-4" />
                                </button>
                                
                                <button @click="eliminarFacultativo(miembro.id, miembro.name)" title="Eliminar" class="p-2 text-zinc-400 hover:text-rose-400 rounded-lg hover:bg-rose-500/10 transition-colors cursor-pointer">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>

                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="modalAltaAbierto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-xl bg-zinc-900 border border-zinc-800 p-6 space-y-4 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex justify-between items-center border-b border-zinc-800 pb-3"><h3 class="font-semibold text-white">Nuevo Facultativo</h3><button @click="modalAltaAbierto = false"><X class="w-5 h-5 text-zinc-500" /></button></div>
                <form @submit.prevent="enviarAlta" class="space-y-4 text-xs">
                    <div><label class="block text-zinc-400 mb-1">Nombre</label><input v-model="formAlta.name" required type="text" placeholder="Dr. Juan Pérez" class="w-full bg-zinc-950 text-white p-2 rounded border border-zinc-800" /></div>
                    <div><label class="block text-zinc-400 mb-1">Email</label><input v-model="formAlta.email" required type="email" placeholder="medico@hospital.com" class="w-full bg-zinc-950 text-white p-2 rounded border border-zinc-800" /></div>
                    <div><label class="block text-zinc-400 mb-1">Rol</label><select v-model="formAlta.rol" class="w-full bg-zinc-950 text-white p-2 rounded border border-zinc-800"><option value="Facultativo">Facultativo</option><option value="Jefe de Servicio">Jefe de Servicio</option></select></div>
                    <div class="flex justify-end gap-2 pt-2"><button type="button" @click="modalAltaAbierto = false" class="px-3 py-1.5 text-zinc-400">Cancelar</button><button type="submit" :disabled="formAlta.processing" class="px-4 py-1.5 bg-emerald-500 text-zinc-950 font-bold rounded">Crear</button></div>
                </form>
            </div>
        </div>

        <div v-if="modalEditarAbierto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-xl bg-zinc-900 border border-emerald-500/30 p-6 space-y-4 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex justify-between items-center border-b border-zinc-800 pb-3"><h3 class="font-semibold text-white flex items-center gap-2"><Pencil class="w-4 h-4 text-emerald-400" /> Editar Facultativo</h3><button @click="modalEditarAbierto = false"><X class="w-5 h-5 text-zinc-500" /></button></div>
                
                <form @submit.prevent="enviarEdicion" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-zinc-400 uppercase mb-1">Nombre Completo</label>
                        <input v-model="formEditar.name" required type="text" class="w-full bg-zinc-950 text-white p-2.5 rounded border border-zinc-800 focus:border-emerald-500 focus:outline-none" />
                        <span v-if="formEditar.errors.name" class="text-rose-400 mt-1 block">{{ formEditar.errors.name }}</span>
                    </div>

                    <div>
                        <label class="block text-zinc-400 uppercase mb-1">Correo Corporativo</label>
                        <input v-model="formEditar.email" required type="email" class="w-full bg-zinc-950 text-white p-2.5 rounded border border-zinc-800 focus:border-emerald-500 focus:outline-none" />
                        <span v-if="formEditar.errors.email" class="text-rose-400 mt-1 block">{{ formEditar.errors.email }}</span>
                    </div>

                    <div>
                        <label class="block text-zinc-400 uppercase mb-1">Rango / Permisos</label>
                        <select v-model="formEditar.rol" class="w-full bg-zinc-950 text-white p-2.5 rounded border border-zinc-800 focus:border-emerald-500 focus:outline-none">
                            <option value="Facultativo">Facultativo (Acceso estándar)</option>
                            <option value="Jefe de Servicio">Jefe de Servicio (Control total)</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-zinc-800">
                        <button type="button" @click="modalEditarAbierto = false" class="px-4 py-2 text-zinc-400 hover:text-white">Cancelar</button>
                        <button type="submit" :disabled="formEditar.processing" class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold rounded cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>