<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Activity, ShieldCheck, Lock, ArrowRight, Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    user: Object
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

const mostrarPassword = ref(false);

const enviarNuevaPassword = () => {
    // Apuntamos a la ruta POST que definiste en web.php para guardar la contraseña
    form.post(`/bienvenido/${props.user.id}`);
};
</script>

<template>
    <Head title="Activa tu cuenta - CliniCore" />

    <div class="min-h-screen bg-zinc-950 flex flex-col justify-center items-center p-4 relative overflow-hidden">
        
        <!-- Elementos decorativos de fondo -->
        <div class="absolute top-0 left-0 w-full h-96 bg-emerald-500/10 blur-[150px] -z-10 rounded-full"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-blue-500/10 blur-[150px] -z-10 rounded-full"></div>

        <div class="w-full max-w-md">
            
            <!-- Logo Header -->
            <div class="flex items-center justify-center gap-3 mb-8">
                <div class="p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-emerald-400">
                    <Activity class="w-8 h-8" />
                </div>
                <span class="text-3xl font-black text-white tracking-wider">CliniCore</span>
            </div>

            <!-- Caja Principal -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
                
                <div class="absolute top-0 right-0 p-6 opacity-5">
                    <ShieldCheck class="w-32 h-32" />
                </div>

                <div class="relative z-10">
                    <h1 class="text-2xl font-bold text-white mb-2">Bienvenido, {{ user.name }}</h1>
                    <p class="text-sm text-zinc-400 mb-8">
                        Has sido invitado a formar parte del sistema de gestión operativa del servicio de <strong class="text-emerald-400 font-medium">Cardiología</strong>. <br><br>
                        Para activar tu cuenta, por favor, establece tu contraseña personal de acceso.
                    </p>

                    <form @submit.prevent="enviarNuevaPassword" class="space-y-5">
                        
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1.5">Nueva Contraseña</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <Lock class="h-4 w-4 text-zinc-500" />
                                </div>
                                <input 
                                    :type="mostrarPassword ? 'text' : 'password'" 
                                    v-model="form.password" 
                                    required 
                                    class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl pl-10 pr-10 py-3 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                    placeholder="Mínimo 8 caracteres"
                                />
                                <button type="button" @click="mostrarPassword = !mostrarPassword" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-zinc-500 hover:text-white cursor-pointer">
                                    <Eye v-if="!mostrarPassword" class="w-4 h-4" />
                                    <EyeOff v-else class="w-4 h-4" />
                                </button>
                            </div>
                            <span v-if="form.errors.password" class="text-xs text-rose-400 block mt-1.5">{{ form.errors.password }}</span>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-1.5">Repite la contraseña</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <Lock class="h-4 w-4 text-zinc-500" />
                                </div>
                                <input 
                                    :type="mostrarPassword ? 'text' : 'password'" 
                                    v-model="form.password_confirmation" 
                                    required 
                                    class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl pl-10 py-3 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                    placeholder="Confirma la contraseña"
                                />
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-zinc-800/80">
                            <button type="submit" :disabled="form.processing" class="w-full bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black text-sm py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20 disabled:opacity-50 flex items-center justify-center gap-2 group cursor-pointer">
                                <span>Activar y acceder al panel</span>
                                <ArrowRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-6 text-center text-xs text-zinc-600 flex justify-center items-center gap-2">
                <ShieldCheck class="w-3.5 h-3.5" />
                Conexión segura cifrada de extremo a extremo
            </div>

        </div>
    </div>
</template>