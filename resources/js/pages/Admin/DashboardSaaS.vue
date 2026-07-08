<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { Activity, Building2, Layers, Users, Sparkles, ArrowRight, ShieldCheck } from '@lucide/vue';

defineProps({ kpis_globales: Object });
const adminName = usePage().props.auth.user.name;
</script>

<template>
    <Head title="SaaS Command Center" />

    <div class="space-y-8 animate-in fade-in duration-300">
        
        <div class="border-b border-zinc-800 pb-6 flex justify-between items-end">
            <div>
                <span class="text-xs font-mono text-indigo-400 font-bold block mb-1">PROPIETARIO PLATAFORMA — NIVEL 0</span>
                <h1 class="text-3xl font-black text-white">Vista General de Negocio</h1>
            </div>
            <span class="text-xs font-mono bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-3 py-1 rounded-full">Instancia: Producción Sescam</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl relative overflow-hidden">
                <div class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2 flex justify-between">Hospitales Clientes <Building2 class="w-4 h-4 text-indigo-400"/></div>
                <div class="text-4xl font-black text-white font-mono">{{ kpis_globales.hospitales_activos }}</div>
            </div>

            <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl relative overflow-hidden">
                <div class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2 flex justify-between">Unidades (Tenants) <Layers class="w-4 h-4 text-emerald-400"/></div>
                <div class="text-4xl font-black text-white font-mono">{{ kpis_globales.tenants_desplegados }}</div>
            </div>

            <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl relative overflow-hidden">
                <div class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2 flex justify-between">Licencias Médicas <Users class="w-4 h-4 text-blue-400"/></div>
                <div class="text-4xl font-black text-white font-mono">{{ kpis_globales.licencias_totales }}</div>
            </div>

            <div class="p-6 bg-zinc-900 border border-zinc-800 rounded-2xl relative overflow-hidden">
                <div class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2 flex justify-between">Guardias procesadas <Sparkles class="w-4 h-4 text-amber-400"/></div>
                <div class="text-4xl font-black text-amber-400 font-mono">{{ kpis_globales.guardias_calculadas_historico }}</div>
            </div>
        </div>

        <div class="p-8 rounded-3xl bg-linear-to-r from-indigo-950/40 via-zinc-900 to-zinc-900 border border-indigo-500/30 flex flex-col sm:flex-row justify-between items-center gap-6 shadow-2xl">
            <div class="space-y-1 text-center sm:text-left">
                <h3 class="text-lg font-bold text-white flex items-center gap-2 justify-center sm:justify-start">
                    <ShieldCheck class="w-5 h-5 text-indigo-400" /> Despliegue rápido de nueva infraestructura
                </h3>
                <p class="text-xs text-zinc-400">Accede al generador transaccional para dar de alta un nuevo hospital o servicio clínico.</p>
            </div>

            <button @click="router.get('/admin/tenants')" class="px-6 py-3.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-black text-sm transition-all cursor-pointer shrink-0 flex items-center gap-2 shadow-lg shadow-indigo-500/20">
                Abrir Fábrica de Tenants <ArrowRight class="w-4 h-4" />
            </button>
        </div>

    </div>
</template>