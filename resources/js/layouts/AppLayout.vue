<script setup>
import { computed, ref } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { 
    LayoutDashboard, CalendarDays, Users, LogOut, 
    Activity, CalendarClock, Layers, Building2, ShieldAlert,
    Menu, X // Añadimos los iconos para el menú móvil
} from '@lucide/vue';

const page = usePage();
const user = page.props.auth?.user;

// Estado reactivo para controlar el menú en pantallas pequeñas
const menuAbierto = ref(false);

const menuNavegacion = computed(() => {
    if (user?.es_superadmin) {
        return [
            { name: 'Consola Global SaaS', href: '/dashboard', icon: LayoutDashboard },
            { name: 'Fábrica de Tenants', href: '/admin/tenants', icon: Building2 },
            { name: 'Registro de Auditoría', href: '/auditoria', icon: ShieldAlert },
        ];
    }

    return [
        { name: 'Panel Principal', href: '/dashboard', icon: LayoutDashboard },
        { name: 'Calendario Completo', href: '/calendario-completo', icon: Layers },
        { name: 'Guardias y Turnos', href: '/guardias', icon: CalendarDays },
        { name: 'Directorio Médico', href: '/personal', icon: Users },
        { name: 'Permisos y Ausencias', href: '/ausencias', icon: CalendarClock },
    ];
});
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100 flex overflow-hidden">
        
        <!-- BACKDROP MÓVIL: Oscurece el fondo cuando el menú está abierto -->
        <div 
            v-if="menuAbierto" 
            @click="menuAbierto = false"
            class="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm md:hidden transition-opacity"
        ></div>
        
        <!-- SIDEBAR: Oculto en móvil (fuera de la pantalla), fijo a la izquierda. En Desktop es estático. -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 border-r border-zinc-800 bg-zinc-900/95 md:bg-zinc-900/50 flex flex-col justify-between transition-transform duration-300 ease-in-out md:translate-x-0 md:static',
                menuAbierto ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div>
                <div class="h-16 flex items-center justify-between px-6 border-b border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-500/10 rounded-lg text-emerald-400">
                            <Activity class="w-6 h-6" />
                        </div>
                        <span class="font-bold tracking-wide text-lg">CliniCore</span>
                    </div>
                    <!-- Botón cerrar solo visible en móvil -->
                    <button @click="menuAbierto = false" class="md:hidden p-1 text-zinc-400 hover:text-white rounded-md hover:bg-zinc-800">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <nav class="p-4 space-y-1">
                    <!-- Añadimos @click="menuAbierto = false" para que el menú se cierre al navegar en móvil -->
                    <Link 
                        v-for="item in menuNavegacion" 
                        :key="item.name" 
                        :href="item.href"
                        @click="menuAbierto = false"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60"
                    >
                        <component :is="item.icon" class="w-5 h-5 text-zinc-400" />
                        {{ item.name }}
                    </Link>
                </nav>
            </div>

            <div class="p-4 border-t border-zinc-800 bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div class="flex flex-col overflow-hidden">
                        <span class="text-sm font-medium truncate">{{ user.name }}</span>
                        <span class="text-xs text-zinc-500 truncate">{{ user.email }}</span>
                    </div>
                    
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button"
                        class="p-2 text-zinc-400 hover:text-rose-400 rounded-lg hover:bg-zinc-800 transition-colors shrink-0"
                        title="Cerrar Sesión"
                    >
                        <LogOut class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ÁREA DE CONTENIDO PRINCIPAL -->
        <main class="flex-1 flex flex-col min-w-0 h-screen">
            <!-- CABECERA: Añadido el botón de menú hamburguesa a la izquierda -->
            <header class="h-16 border-b border-zinc-800 px-4 sm:px-8 flex items-center justify-between bg-zinc-900/20 shrink-0">
                <div class="flex items-center gap-3">
                    <button 
                        @click="menuAbierto = true" 
                        class="md:hidden p-1.5 -ml-1.5 text-zinc-400 hover:text-white rounded-md hover:bg-zinc-800 transition-colors"
                    >
                        <Menu class="w-6 h-6" />
                    </button>
                    <h1 class="text-base sm:text-lg font-semibold text-zinc-200 truncate">
                        <slot name="header">Área Clínica</slot>
                    </h1>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="hidden sm:inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        SaaS v1.0
                    </span>
                    <span class="sm:hidden px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        v1.0
                    </span>
                </div>
            </header>

            <!-- CONTENEDOR VISTAS VUE: Paddings dinámicos -->
            <div class="p-4 sm:p-6 lg:p-8 flex-1 overflow-y-auto">
                <slot />
            </div>
        </main>

        <!-- BANNER FLOTANTE DE MODO DIOS -->
        <div v-if="$page.props.auth.impersonated" class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[9999] animate-bounce">
            <Link 
                href="/impersonate/leave" 
                method="post"
                as="button"
                class="px-4 py-2.5 sm:px-5 sm:py-3 bg-rose-600 hover:bg-rose-500 text-white font-black text-[10px] sm:text-xs uppercase tracking-widest rounded-xl sm:rounded-2xl shadow-[0_0_30px_rgba(225,29,72,0.5)] flex items-center gap-2 border-2 border-rose-400 cursor-pointer transition-all active:scale-95"
            >
                Abandonar Modo Dios
            </Link>
        </div>
        
    </div>
</template>