<script setup>
import { computed, ref } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { 
    LayoutDashboard, CalendarDays, Users, LogOut, 
    Activity, CalendarClock, Layers, Building2, ShieldAlert,
    Menu, X, Bell // Añadimos los iconos para el menú móvil
} from '@lucide/vue';

const page = usePage();
const user = page.props.auth?.user;

// Estado reactivo para controlar el menú en pantallas pequeñas
const menuAbierto = ref(false);

// Estado para controlar si el desplegable de notificaciones está abierto
const mostrarNotificaciones = ref(false);

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

const marcarLeidas = () => {
    router.post('/notificaciones/marcar-leidas', {}, {
        preserveScroll: true,
        onSuccess: () => {
            mostrarNotificaciones.value = false; // Cierra el cajoncito al terminar
        }
    });
};
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
                
                <!-- DENTRO DEL HEADER, A LA DERECHA -->
                <div class="flex items-center gap-4 shrink-0">
                    
                    <!-- SISTEMA DE NOTIFICACIONES -->
                    <div class="relative">
                        <button @click="mostrarNotificaciones = !mostrarNotificaciones" class="relative p-2 text-zinc-400 hover:text-white rounded-lg hover:bg-zinc-800 transition-colors">
                            <Bell class="w-5 h-5" />
                            <!-- Globo rojo del contador (USANDO ? PARA EVITAR CRASHES) -->
                            <span v-if="($page.props.auth?.notificaciones_count || 0) > 0" class="absolute top-1.5 right-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-rose-500 text-[9px] font-bold text-white ring-2 ring-zinc-900">
                                {{ $page.props.auth.notificaciones_count }}
                            </span>
                        </button>

                        <!-- DESPLEGABLE DE NOTIFICACIONES -->
                        <div v-if="mostrarNotificaciones" class="absolute right-0 mt-2 w-80 bg-zinc-900 border border-zinc-800 rounded-xl shadow-2xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2">
                            <div class="p-3 border-b border-zinc-800 bg-zinc-950/50 flex justify-between items-center">
                                <span class="text-sm font-bold text-white">Notificaciones</span>
                                <button 
                                    v-if="($page.props.auth?.notificaciones_count || 0) > 0" 
                                    @click="marcarLeidas"
                                    class="text-[10px] text-emerald-400 hover:text-emerald-300 uppercase font-bold tracking-wider cursor-pointer"
                                >
                                    Marcar leídas
                                </button>
                            </div>
                            
                            <div class="max-h-80 overflow-y-auto">
                                <!-- USANDO ? PARA EVITAR CRASHES SI EL ARRAY NO EXISTE -->
                                <div v-if="!$page.props.auth?.notificaciones || $page.props.auth.notificaciones.length === 0" class="p-6 text-center text-xs text-zinc-500 italic">
                                    No tienes notificaciones nuevas.
                                </div>
                                
                                <div v-else v-for="notificacion in $page.props.auth.notificaciones" :key="notificacion.id" class="p-3 border-b border-zinc-800/50 hover:bg-zinc-800/30 transition-colors flex gap-3">
                                    <div class="mt-0.5 shrink-0">
                                        <div class="w-2 h-2 rounded-full mt-1.5" :class="notificacion.data?.estado === 'aprobada' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-white">{{ notificacion.data?.titulo }}</p>
                                        <p class="text-[11px] text-zinc-400 mt-0.5 leading-tight">{{ notificacion.data?.mensaje }}</p>
                                        <p class="text-[9px] text-zinc-600 font-mono mt-1">{{ notificacion.data?.fecha }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tu badge de SaaS v1.0 -->
                    <span class="hidden sm:inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        SaaS v1.0
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