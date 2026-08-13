<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Activity, Lock, CheckCircle2 } from '@lucide/vue';

const props = defineProps({
    usuario: Object
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

const enviarPassword = () => {
    // Enviamos el formulario conservando la firma de la URL
    form.post(window.location.href, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Activar Cuenta — CliniCore" />

    <div class="min-h-screen bg-zinc-950 text-zinc-100 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-zinc-900 border border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
            
            <div class="flex items-center gap-3 justify-center">
                <div class="p-2.5 bg-emerald-500/10 rounded-xl text-emerald-400 border border-emerald-500/20">
                    <Activity class="w-7 h-7" />
                </div>
                <span class="font-bold tracking-wide text-2xl text-white">CliniCore</span>
            </div>

            <div class="text-center space-y-1">
                <h2 class="text-xl font-bold text-white">Bienvenido, {{ usuario.name }}</h2>
                <p class="text-xs text-zinc-400">
                    Para completar la activación de tu cuenta, establece tu contraseña de acceso personal.
                </p>
            </div>

            <form @submit.prevent="enviarPassword" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Nueva Contraseña</label>
                    <div class="relative">
                        <input 
                            v-model="form.password" 
                            type="password" 
                            required 
                            autofocus
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 pl-10 text-white text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                            placeholder="••••••••"
                        />
                        <Lock class="w-4 h-4 text-zinc-500 absolute left-3.5 top-3.5" />
                    </div>
                    <span v-if="form.errors.password" class="text-xs text-rose-400 mt-1 block font-mono">{{ form.errors.password }}</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-400 uppercase mb-1.5 tracking-wider">Confirmar Contraseña</label>
                    <div class="relative">
                        <input 
                            v-model="form.password_confirmation" 
                            type="password" 
                            required 
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 pl-10 text-white text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                            placeholder="••••••••"
                        />
                        <CheckCircle2 class="w-4 h-4 text-zinc-500 absolute left-3.5 top-3.5" />
                    </div>
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full py-3.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black rounded-xl text-xs uppercase tracking-wider transition-all shadow-lg flex justify-center items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    {{ form.processing ? 'Activando...' : 'Activar Cuenta e Iniciar Sesión' }}
                </button>
            </form>

        </div>
    </div>
</template>