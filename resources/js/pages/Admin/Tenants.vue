<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { 
    Layers, Building2, UserPlus, Mail, ShieldAlert, 
    Sparkles, Key, CheckCircle2, Users, Activity,
    Edit, Trash2, Eye, X, Settings2
} from '@lucide/vue';

const props = defineProps({
    hospitales: Array // Se espera que traiga sus servicios y 'usuarios' anidados
});

const page = usePage();

// ==========================================
// FORMULARIO: ALTA DE NUEVO TENANT
// ==========================================
const form = useForm({
    hospital_nombre: '',
    especialidad_nombre: '',
    jefe_nombre: '',
    jefe_email: ''
});

const desplegarUnidad = () => {
    form.post('/admin/tenants', {
        preserveScroll: true,
        onSuccess: () => form.reset()
    });
};

// ==========================================
// ESTADO Y MODALES: GESTIÓN DE USUARIOS
// ==========================================
const tenantSeleccionado = ref(null);
const mostrarModalUsuarios = ref(false);

const abrirGestionUsuarios = (hospital) => {
    tenantSeleccionado.value = hospital;
    // Pre-asignamos la especialidad para el formulario de nuevo usuario
    formNewUser.especialidad_id = hospital.servicios[0]?.id; 
    mostrarModalUsuarios.value = true;
    modoCrearUsuario.value = false;
    usuarioEditando.value = null;
};

const cerrarModal = () => {
    mostrarModalUsuarios.value = false;
    setTimeout(() => tenantSeleccionado.value = null, 300);
};

// ==========================================
// ACCIONES: ELIMINAR Y EDITAR TENANTS
// ==========================================
const eliminarTenant = (id, nombre) => {
    if (confirm(`⚠️ PELIGRO: ¿Estás seguro de que quieres eliminar TODA la infraestructura de ${nombre}? Se borrarán sus médicos, guardias y configuraciones.`)) {
        router.delete(`/admin/tenants/${id}`, { preserveScroll: true });
    }
};

const formEditTenant = useForm({ id: '', nombre: '' });
const modalEditTenant = ref(false);

const abrirEditTenant = (hospital) => {
    formEditTenant.id = hospital.id;
    formEditTenant.nombre = hospital.nombre;
    modalEditTenant.value = true;
};

const actualizarTenant = () => {
    formEditTenant.put(`/admin/tenants/${formEditTenant.id}`, {
        preserveScroll: true,
        onSuccess: () => modalEditTenant.value = false
    });
}; // <-- ¡Aquí faltaba cerrar bien esta llave en tu código!

// ==========================================
// FORMULARIOS DE USUARIO (CREAR Y EDITAR)
// ==========================================
const modoCrearUsuario = ref(false);
const usuarioEditando = ref(null);

const formNewUser = useForm({
    name: '',
    email: '',
    especialidad_id: '',
    role: 'Facultativo'
});

const formEditUser = useForm({
    name: '',
    email: '',
    role: ''
});

const guardarNuevoUsuario = () => {
    formNewUser.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => {
            formNewUser.reset('name', 'email', 'password');
            modoCrearUsuario.value = false;
        }
    });
};

const iniciarEdicionUsuario = (user) => {
    usuarioEditando.value = user.id;
    formEditUser.name = user.name;
    formEditUser.email = user.email;
    formEditUser.role = user.roles?.[0]?.name || 'Facultativo';
};

const actualizarUsuario = (id) => {
    formEditUser.put(`/admin/users/${id}`, {
        preserveScroll: true,
        onSuccess: () => usuarioEditando.value = null
    });
};

const eliminarUsuario = (id, nombre) => {
    if (confirm(`¿Expulsar al usuario ${nombre} del sistema? Perderá el acceso inmediatamente.`)) {
        router.delete(`/admin/users/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Infraestructura SaaS" />

    <div class="space-y-6 max-w-7xl mx-auto p-4 sm:p-6 relative">
        
        <div class="p-6 bg-gradient-to-r from-zinc-900 via-zinc-900 to-indigo-950/40 border border-zinc-800 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-xl">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-mono font-bold">
                    <Activity class="w-3.5 h-3.5 animate-pulse" />
                    CONSOLA CENTRAL GLOBAL (SUPERADMIN)
                </div>
                <h2 class="text-xl font-bold text-white tracking-tight">Fábrica de Despliegue Multi-Tenant</h2>
                <p class="text-sm text-zinc-400 max-w-2xl">
                    Control absoluto sobre la infraestructura. Aísla nuevos centros, audita usuarios activos y gestiona los recursos de tus clientes desde este panel de mando.
                </p>
            </div>
        </div>

        <div v-if="page.props.flash?.flash_toast" class="p-5 rounded-xl bg-indigo-500/10 border-2 border-indigo-500/40 text-indigo-200 shadow-2xl space-y-2 animate-in fade-in slide-in-from-top-4">
            <div class="flex items-center gap-2 font-black text-sm text-indigo-400 uppercase tracking-wider">
                <Key class="w-4 h-4" />
                Notificación del Sistema
            </div>
            <p class="text-xs font-mono bg-zinc-950 p-3 rounded-lg border border-zinc-800 text-zinc-300">
                {{ page.props.flash.flash_toast.mensaje }}
            </p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            
            <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl space-y-4 h-fit shadow-xl">
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <UserPlus class="w-4 h-4 text-indigo-400" /> Desplegar Contrato SaaS
                </h3>
                
                <form @submit.prevent="desplegarUnidad" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-zinc-400 uppercase tracking-wide mb-1 font-semibold">Institución Médica</label>
                        <div class="relative">
                            <Building2 class="w-4 h-4 text-zinc-600 absolute left-3 top-2.5" />
                            <input v-model="form.hospital_nombre" required type="text" placeholder="Ej: Hospital General de Toledo" class="w-full bg-zinc-950 text-white pl-9 p-2.5 rounded border border-zinc-800 focus:border-indigo-500 focus:outline-none font-medium" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-zinc-400 uppercase tracking-wide mb-1 font-semibold">Unidad / Especialidad</label>
                        <input v-model="form.especialidad_nombre" required type="text" placeholder="Ej: Oftalmología Clínica" class="w-full bg-zinc-950 text-white p-2.5 rounded border border-zinc-800 focus:border-indigo-500 focus:outline-none font-medium" />
                    </div>
                    <div class="border-t border-zinc-800/60 pt-3">
                        <label class="block text-zinc-400 uppercase tracking-wide mb-1 font-semibold">Nombre del Director / Jefe</label>
                        <input v-model="form.jefe_nombre" required type="text" placeholder="Ej: Dra. Allison Cameron" class="w-full bg-zinc-950 text-white p-2.5 rounded border border-zinc-800 focus:border-indigo-500 focus:outline-none font-medium" />
                    </div>
                    <div>
                        <label class="block text-zinc-400 uppercase tracking-wide mb-1 font-semibold">Email Corporativo</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-zinc-600 absolute left-3 top-2.5" />
                            <input v-model="form.jefe_email" required type="email" placeholder="cameron@hospital.es" class="w-full bg-zinc-950 text-white pl-9 p-2.5 rounded border border-zinc-800 focus:border-indigo-500 focus:outline-none font-mono" />
                        </div>
                        <span v-if="form.errors.jefe_email" class="text-rose-400 mt-1 block">{{ form.errors.jefe_email }}</span>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full py-3 bg-indigo-500 hover:bg-indigo-400 disabled:opacity-50 text-zinc-950 font-black rounded-xl cursor-pointer transition-all flex items-center justify-center gap-2 text-sm shadow-lg shadow-indigo-500/10">
                        <Sparkles class="w-4 h-4 fill-zinc-950" />
                        {{ form.processing ? 'Provisionando...' : 'Avanzar Aislamiento' }}
                    </button>
                </form>
            </div>

            <div class="xl:col-span-2 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-xl space-y-4">
                <h3 class="font-bold text-white text-base flex items-center gap-2">
                    <Layers class="w-4 h-4 text-indigo-400" /> Nodos de Infraestructura (Clientes)
                </h3>

                <div v-if="hospitales.length === 0" class="text-xs text-zinc-500 italic p-12 text-center border border-dashed border-zinc-800 rounded-xl">
                    No hay ningún hospital registrado en la base de datos central.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="h in hospitales" :key="h.id" class="p-4 bg-zinc-950 rounded-xl border border-zinc-800/80 flex flex-col justify-between hover:border-zinc-700 transition-all group">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-[9px] font-mono text-zinc-500 uppercase tracking-widest block">Tenant ID: #{{ String(h.id).padStart(4, '0') }}</span>
                                <h4 class="text-white font-bold text-base mt-0.5 group-hover:text-indigo-400 transition-colors">{{ h.nombre }}</h4>
                            </div>
                            <div class="flex items-center gap-1 opacity-20 group-hover:opacity-100 transition-opacity">
                                <button @click="abrirEditTenant(h)" class="p-1.5 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-indigo-400 rounded-md transition-colors" title="Editar Nombre">
                                    <Edit class="w-3.5 h-3.5" />
                                </button>
                                <button @click="eliminarTenant(h.id, h.nombre)" class="p-1.5 bg-zinc-900 hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 rounded-md transition-colors" title="Destruir Tenant">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-2 space-y-1.5">
                            <div v-for="s in h.servicios" :key="s.id" class="text-xs text-zinc-400 flex items-center justify-between bg-zinc-900 px-2.5 py-1.5 rounded border border-zinc-800/40">
                                <span class="font-medium text-zinc-300 flex items-center gap-1.5">
                                    <ShieldAlert class="w-3 h-3 text-indigo-500" /> {{ s.nombre }}
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500" :class="(h.usuarios?.length || 0) >= (s.limite || 999) ? 'text-rose-400 font-bold' : ''">
                                    Licencias: {{ h.usuarios?.length || h.total_medicos || 0 }} / {{ s.limite ?? '∞' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-zinc-900 flex justify-between items-center text-xs">
                            <span class="flex items-center gap-1.5 text-zinc-500">
                                <Users class="w-3.5 h-3.5 text-zinc-600" />
                                Usuarios registrados
                            </span>
                            <button @click="abrirGestionUsuarios(h)" class="px-3 py-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 hover:border-indigo-500/50 rounded-lg transition-all font-semibold flex items-center gap-1.5">
                                <Eye class="w-3.5 h-3.5" /> Auditar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="mostrarModalUsuarios" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
            <div class="bg-zinc-900 border border-zinc-800 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <div class="p-5 border-b border-zinc-800 flex justify-between items-center bg-zinc-950">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <Users class="w-5 h-5 text-indigo-400" />
                            Personal de {{ tenantSeleccionado.nombre }}
                        </h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Gestión directa de accesos y cuentas del cliente.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="modoCrearUsuario = !modoCrearUsuario" class="px-3 py-1.5 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold text-xs rounded-lg transition-all flex items-center gap-1.5 cursor-pointer">
                            <UserPlus class="w-3.5 h-3.5" /> {{ modoCrearUsuario ? 'Cancelar' : 'Nuevo Usuario' }}
                        </button>
                        <button @click="cerrarModal" class="p-2 bg-zinc-900 hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 rounded-xl transition-colors cursor-pointer">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div v-if="$page.props.errors.licencia" class="mx-5 mt-4 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs rounded-lg flex items-center gap-2">
                    <ShieldAlert class="w-4 h-4 shrink-0" />
                    {{ $page.props.errors.licencia }}
                </div>

                <div v-if="modoCrearUsuario" class="p-5 border-b border-zinc-800 bg-zinc-900/50">
                    <form @submit.prevent="guardarNuevoUsuario" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <input v-model="formNewUser.name" required type="text" placeholder="Nombre completo" class="w-full bg-zinc-950 text-white p-2 rounded-lg border border-zinc-800 text-sm focus:border-indigo-500 focus:outline-none" />
                            <!-- Chivato de error de validación -->
                            <span v-if="formNewUser.errors.name" class="text-rose-400 text-[10px] mt-1 block">{{ formNewUser.errors.name }}</span>
                        </div>
                        <div>
                            <input v-model="formNewUser.email" required type="email" placeholder="Email" class="w-full bg-zinc-950 text-white p-2 rounded-lg border border-zinc-800 text-sm focus:border-indigo-500 focus:outline-none" />
                            <span v-if="formNewUser.errors.email" class="text-rose-400 text-[10px] mt-1 block">{{ formNewUser.errors.email }}</span>
                        </div>
                        <div class="flex flex-col">
                            <div class="flex gap-2">
                                <select v-model="formNewUser.role" class="w-full bg-zinc-950 text-white p-2 rounded-lg border border-zinc-800 text-sm focus:border-indigo-500 focus:outline-none">
                                    <!-- AHORA SÍ: Usamos Facultativo -->
                                    <option value="Facultativo">Facultativo (Estándar)</option>
                                    <option value="Jefe de Servicio">Jefe de Servicio (Admin)</option>
                                </select>
                                <button type="submit" :disabled="formNewUser.processing" class="px-4 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold rounded-lg transition-colors text-sm cursor-pointer disabled:opacity-50">
                                    Guardar
                                </button>
                            </div>
                            <span v-if="formNewUser.errors.role" class="text-rose-400 text-[10px] mt-1 block">{{ formNewUser.errors.role }}</span>
                            <span v-if="formNewUser.errors.especialidad_id" class="text-rose-400 text-[10px] mt-1 block">{{ formNewUser.errors.especialidad_id }}</span>
                        </div>
                    </form>
                </div>

                <div class="p-5 overflow-y-auto space-y-3 bg-zinc-900 flex-1">
                    <div v-if="!tenantSeleccionado.usuarios || tenantSeleccionado.usuarios.length === 0" class="text-center p-8 text-zinc-500 text-sm italic">
                        No hay usuarios registrados en esta unidad.
                    </div>
                    
                    <div v-else v-for="user in tenantSeleccionado.usuarios" :key="user.id" class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-zinc-950 border border-zinc-800 rounded-xl hover:border-zinc-700 transition-colors gap-3">
                        
                        <template v-if="usuarioEditando !== user.id">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 font-bold text-xs uppercase shrink-0">
                                    {{ user.name.substring(0, 2) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ user.name }}</p>
                                    <p class="text-[10px] text-zinc-500 font-mono">{{ user.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 justify-end">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold" 
                                    :class="user.roles?.[0]?.name === 'Jefe de Servicio' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'">
                                    {{ user.roles?.[0]?.name || 'Facultativo' }}
                                </span>
                                
                                <div class="flex items-center border-l border-zinc-800 pl-3 gap-1">
                                    <button @click="router.post('/admin/impersonate/' + user.id)" class="p-1.5 text-zinc-500 hover:bg-emerald-500/20 hover:text-emerald-400 rounded-md transition-all flex items-center gap-1 text-[10px] font-bold mr-1 cursor-pointer" title="Entrar como este usuario">
                                        <Key class="w-3.5 h-3.5" /> Entrar
                                    </button>

                                    <button @click="iniciarEdicionUsuario(user)" class="p-1.5 text-zinc-500 hover:bg-zinc-800 hover:text-indigo-400 rounded-md transition-all cursor-pointer" title="Editar">
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button @click="eliminarUsuario(user.id, user.name)" class="p-1.5 text-zinc-500 hover:bg-rose-500/20 hover:text-rose-400 rounded-md transition-all cursor-pointer" title="Eliminar cuenta">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2 w-full">
                                <input v-model="formEditUser.name" type="text" class="bg-zinc-900 text-white p-1.5 rounded border border-zinc-700 text-xs" />
                                <input v-model="formEditUser.email" type="email" class="bg-zinc-900 text-white p-1.5 rounded border border-zinc-700 text-xs" />
                                <select v-model="formEditUser.role" class="bg-zinc-900 text-white p-1.5 rounded border border-zinc-700 text-xs">
                                    <option value="Facultativo">Facultativo</option>
                                    <option value="Jefe de Servicio">Jefe de Servicio</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="actualizarUsuario(user.id)" class="px-3 py-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded text-xs font-bold hover:bg-emerald-500 hover:text-zinc-900 transition-colors cursor-pointer">Guardar</button>
                                <button @click="usuarioEditando = null" class="px-3 py-1.5 bg-zinc-800 text-zinc-300 rounded text-xs hover:bg-zinc-700 cursor-pointer">Cancelar</button>
                            </div>
                        </template>

                    </div>
                </div>
            </div>
        </div>

        <div v-if="modalEditTenant" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
            <div class="bg-zinc-900 border border-zinc-800 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden p-6">
                <h3 class="text-lg font-bold text-white mb-4">Editar Institución</h3>
                <form @submit.prevent="actualizarTenant" class="space-y-4">
                    <div>
                        <label class="block text-zinc-400 text-xs uppercase font-semibold mb-1">Nombre del Hospital</label>
                        <input v-model="formEditTenant.nombre" required type="text" class="w-full bg-zinc-950 text-white p-2.5 rounded-lg border border-zinc-800 focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div class="flex gap-3 justify-end pt-2">
                        <button type="button" @click="modalEditTenant = false" class="px-4 py-2 text-sm text-zinc-400 hover:text-white transition-colors cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="formEditTenant.processing" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-bold rounded-lg transition-all text-sm shadow-lg shadow-indigo-500/20 cursor-pointer">
                            {{ formEditTenant.processing ? 'Guardando...' : 'Actualizar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>

<style>
.animate-in {
    animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
</style>