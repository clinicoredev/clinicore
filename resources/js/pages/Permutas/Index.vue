<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeftRight,
    CheckCircle2,
    Clock,
    PlusCircle,
    ShieldAlert,
    Trash2,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

interface SolicitudPermutaItem {
    id: number;
    tipo_solicitud?: 'permuta' | 'cobertura_vacante';
    guardia_origen_id: number;
    guardia_destino_id: number | null;
    solicitante_id: number;
    destinatario_id: number | null;
    solicitante: string;
    destinatario: string;
    fecha_origen: string;
    fecha_destino: string;
    colectivo: 'adjuntos' | 'residentes';
    estado: 'pendiente' | 'pendiente_companero' | 'pendiente_aprobacion' | 'abierta_bolsa' | 'aprobada' | 'rechazada' | 'cancelada';
    motivo: string;
    aceptado_destinatario_at?: string | null;
    revisor: string | null;
    resuelto_at: string | null;
    puede_aceptar_companero?: boolean;
    puede_postular_vacante?: boolean;
    puede_firmar: boolean;
    puede_cancelar?: boolean;
    impacto_equidad?: {
        horas_origen: number;
        horas_destino: number;
        delta_destinatario: number;
        solicitante_horas_anio: number;
        destinatario_horas_anio: number | null;
        supera_48h_semana: boolean;
    } | null;
}

interface GuardiaPropiaOption {
    id: number;
    fecha: string;
    fecha_formateada: string;
    etiqueta: string;
    colectivo?: 'adjuntos' | 'residentes';
}

interface GuardiaCompaneroOption {
    id: number;
    user_id: number;
    facultativo: string;
    fecha: string;
    fecha_formateada: string;
    etiqueta: string;
}

interface ConflictoDetalle {
    user_id: number;
    facultativo: string;
    fecha: string;
    fecha_formateada: string;
    tipo: string;
    severidad: 'bloqueante' | 'advertencia';
    mensaje: string;
}

interface DiagnosticoRespuesta {
    valido: boolean;
    bloqueante: boolean;
    forzable: boolean;
    conflictos: ConflictoDetalle[];
    mensaje_resumen: string | null;
}

const props = defineProps<{
    solicitudes: SolicitudPermutaItem[];
    mis_guardias: GuardiaPropiaOption[];
    guardias_companeros: GuardiaCompaneroOption[];
    colectivo_usuario: 'adjuntos' | 'residentes';
    permisos: {
        es_jefe: boolean;
        es_tutor: boolean;
        es_revisor: boolean;
    };
}>();

const page = usePage();
const conflictoError = computed(() => (page.props.errors as Record<string, string> | undefined)?.conflicto ?? '');
const cerrarConflictoError = () => {
    const errors = page.props.errors as Record<string, string> | undefined;
    if (errors?.conflicto) {
        delete errors.conflicto;
    }
};
const usuarioActualId = computed(() => Number((page.props.auth as any)?.user?.id ?? 0));
const modoFormulario = ref<'permuta' | 'vacante'>('permuta');
const filtroEstado = ref<'todos' | 'pendiente' | 'aprobada' | 'rechazada'>('todos');

const solicitudesFiltradas = computed(() => {
    if (filtroEstado.value === 'todos') {
        return props.solicitudes ?? [];
    }
    if (filtroEstado.value === 'pendiente') {
        return (props.solicitudes ?? []).filter((s) =>
            ['pendiente', 'pendiente_companero', 'pendiente_aprobacion', 'abierta_bolsa'].includes(s.estado),
        );
    }
    return (props.solicitudes ?? []).filter((s) => s.estado === filtroEstado.value);
});

const form = useForm({
    guardia_origen_id: '' as number | string,
    guardia_destino_id: '' as number | string,
    motivo: '',
});

const cargandoDiagnostico = ref(false);
const diagnostico = ref<DiagnosticoRespuesta | null>(null);

const obtenerCsrfToken = (): string => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content') || '';
    }
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const basePathVerificacion = computed(() => {
    const origen = (props.mis_guardias ?? []).find((g) => Number(g.id) === Number(form.guardia_origen_id));
    const col = origen?.colectivo ?? props.colectivo_usuario;
    return col === 'residentes' ? '/residentes/guardias' : '/guardias';
});

watch(
    () => [form.guardia_origen_id, form.guardia_destino_id],
    async ([origenId, destinoId]) => {
        if (!origenId || !destinoId || Number(origenId) === Number(destinoId)) {
            diagnostico.value = null;
            return;
        }

        cargandoDiagnostico.value = true;
        try {
            const resp = await fetch(`${basePathVerificacion.value}/verificar-conflicto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': obtenerCsrfToken(),
                },
                body: JSON.stringify({
                    modo: 'permuta',
                    origen_id: Number(origenId),
                    destino_id: Number(destinoId),
                    distancia_minima_dias: 1,
                }),
            });
            if (resp.ok) {
                diagnostico.value = (await resp.json()) as DiagnosticoRespuesta;
            }
        } catch {
            diagnostico.value = null;
        } finally {
            cargandoDiagnostico.value = false;
        }
    },
);

const enviarSolicitud = () => {
    const endpoint = modoFormulario.value === 'vacante' ? '/permutas/vacantes' : '/permutas';
    form.post(endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            diagnostico.value = null;
        },
    });
};

const postularVacante = (id: number) => {
    router.patch(`/permutas/${id}/postular-vacante`, {}, { preserveScroll: true });
};

const responderCompanero = (id: number, accion: 'aceptar' | 'rechazar') => {
    router.patch(`/permutas/${id}/responder-companero`, { accion }, { preserveScroll: true });
};

const resolverSolicitud = (id: number, estado: 'aprobada' | 'rechazada') => {
    router.patch(`/permutas/${id}/resolver`, { estado }, { preserveScroll: true });
};

const cancelarSolicitud = (id: number) => {
    if (confirm('¿Deseas cancelar y eliminar esta solicitud de permuta?')) {
        router.delete(`/permutas/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Solicitudes de Intercambio de Guardias" />

    <div class="space-y-6 max-w-6xl mx-auto">
        <!-- BANNER ERRORES -->
        <div
            v-if="conflictoError"
            class="p-4 bg-rose-500/20 border-2 border-rose-500/50 rounded-2xl flex items-center gap-3 text-rose-200"
        >
            <ShieldAlert class="w-6 h-6 text-rose-400 shrink-0" />
            <div class="text-xs sm:text-sm font-medium">
                {{ conflictoError }}
            </div>
            <button
                type="button"
                @click="cerrarConflictoError"
                class="ml-auto p-1 hover:bg-rose-500/20 rounded-lg text-rose-400 cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>
        </div>

        <!-- CABECERA -->
        <div class="p-6 bg-gradient-to-r from-zinc-900 via-zinc-900 to-indigo-950/30 border border-zinc-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-[10px] font-mono font-bold mb-2">
                    <ArrowLeftRight class="w-3.5 h-3.5" /> AUTO-SERVICIO CON VALIDACIÓN CLÍNICA
                </div>
                <h2 class="text-xl font-bold text-white tracking-tight">
                    Solicitudes de Intercambio de Guardias
                </h2>
                <p class="text-xs sm:text-sm text-zinc-400 mt-1">
                    Propón permutas válidas con tus compañeros de colectivo sujetas a validación de salientes y firma de Jefatura / Tutoría.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- FORMULARIO NUEVA SOLICITUD O VACANTE -->
            <div class="p-5 bg-zinc-900 border border-zinc-800 rounded-2xl h-fit space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-bold text-white text-sm flex items-center gap-2">
                        <PlusCircle class="w-4 h-4 text-emerald-400" />
                        {{ modoFormulario === 'vacante' ? 'Publicar Vacante en Bolsa' : 'Proponer Intercambio' }}
                    </h3>
                </div>

                <div class="grid grid-cols-2 gap-1 bg-zinc-950 p-1 rounded-xl border border-zinc-800 text-[11px] font-bold">
                    <button
                        type="button"
                        @click="modoFormulario = 'permuta'"
                        class="py-1.5 rounded-lg cursor-pointer transition-colors"
                        :class="modoFormulario === 'permuta' ? 'bg-zinc-800 text-white' : 'text-zinc-500 hover:text-zinc-300'"
                    >
                        Permuta 1 a 1
                    </button>
                    <button
                        type="button"
                        @click="modoFormulario = 'vacante'; diagnostico = null"
                        class="py-1.5 rounded-lg cursor-pointer transition-colors"
                        :class="modoFormulario === 'vacante' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'text-zinc-500 hover:text-zinc-300'"
                    >
                        Bolsa Vacantes
                    </button>
                </div>

                <form @submit.prevent="enviarSolicitud" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-400 mb-1">
                            {{ modoFormulario === 'vacante' ? 'Turno Descubierto / Baja a Publicar' : 'Tu Guardia a Ceder' }}
                        </label>
                        <select
                            v-model="form.guardia_origen_id"
                            required
                            class="w-full bg-zinc-950 text-white rounded-xl border border-zinc-800 p-2.5 outline-none focus:border-emerald-500"
                        >
                            <option class="bg-zinc-900" value="" disabled>Selecciona uno de tus turnos...</option>
                            <option
                                class="bg-zinc-900"
                                v-for="g in mis_guardias"
                                :key="g.id"
                                :value="g.id"
                            >
                                {{ g.fecha_formateada }} — {{ g.etiqueta }}
                            </option>
                        </select>
                    </div>

                    <div v-if="modoFormulario === 'permuta'">
                        <label class="block text-[10px] font-bold uppercase text-zinc-400 mb-1">
                            Turno del Compañero Solicitado
                        </label>
                        <select
                            v-model="form.guardia_destino_id"
                            required
                            class="w-full bg-zinc-950 text-white rounded-xl border border-zinc-800 p-2.5 outline-none focus:border-emerald-500"
                        >
                            <option class="bg-zinc-900" value="" disabled>Selecciona el turno de destino...</option>
                            <option
                                class="bg-zinc-900"
                                v-for="gc in guardias_companeros"
                                :key="gc.id"
                                :value="gc.id"
                            >
                                {{ gc.fecha_formateada }} — {{ gc.facultativo }} ({{ gc.etiqueta }})
                            </option>
                        </select>
                    </div>
                    <div v-else class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-[11px] text-amber-200">
                        Se notificará a todos los compañeros disponibles de tu mismo colectivo para que un voluntario sin conflicto de saliente pueda cubrir el turno.
                    </div>

                    <div v-if="cargandoDiagnostico" class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-400 font-mono">
                        Comprobando conflictos de salientes y ausencias...
                    </div>
                    <div
                        v-else-if="diagnostico && diagnostico.valido"
                        class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center gap-2"
                    >
                        <CheckCircle2 class="w-4 h-4 shrink-0" />
                        <span>Permuta libre de conflictos clínicos.</span>
                    </div>
                    <div
                        v-else-if="diagnostico && diagnostico.conflictos.length > 0"
                        class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/40 text-rose-200 space-y-1"
                    >
                        <div class="flex items-center gap-1.5 font-bold text-rose-300">
                            <AlertTriangle class="w-4 h-4 shrink-0" />
                            <span>Conflicto detectado:</span>
                        </div>
                        <p v-for="(c, idx) in diagnostico.conflictos" :key="idx" class="text-[11px]">
                            {{ c.facultativo }}: {{ c.mensaje }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-400 mb-1">
                            Motivo / Observaciones
                        </label>
                        <input
                            v-model="form.motivo"
                            type="text"
                            placeholder="Ej: Acuerdo mutuo por compromiso familiar..."
                            class="w-full bg-zinc-950 text-white rounded-xl border border-zinc-800 p-2.5 outline-none focus:border-emerald-500"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || (modoFormulario === 'permuta' && Boolean(diagnostico?.bloqueante))"
                        class="w-full py-2.5 font-black rounded-xl transition-colors cursor-pointer disabled:opacity-50"
                        :class="modoFormulario === 'vacante'
                            ? 'bg-amber-500 hover:bg-amber-400 text-zinc-950'
                            : 'bg-emerald-500 hover:bg-emerald-400 text-zinc-950'"
                    >
                        {{ modoFormulario === 'vacante' ? 'Publicar en Bolsa de Cobertura' : 'Solicitar Intercambio' }}
                    </button>
                </form>
            </div>

            <!-- LISTADO DE SOLICITUDES -->
            <div class="lg:col-span-2 bg-zinc-900 border border-zinc-800 rounded-2xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-800 pb-3">
                    <h3 class="font-bold text-white text-sm flex items-center gap-2">
                        <Clock class="w-4 h-4 text-indigo-400" /> Historial y Bandeja de Aprobación
                    </h3>
                    <div class="flex gap-1 bg-zinc-950 p-1 rounded-xl border border-zinc-800 text-xs">
                        <button
                            type="button"
                            @click="filtroEstado = 'todos'"
                            :class="filtroEstado === 'todos' ? 'bg-zinc-800 text-white' : 'text-zinc-500'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer"
                        >
                            Todas
                        </button>
                        <button
                            type="button"
                            @click="filtroEstado = 'pendiente'"
                            :class="filtroEstado === 'pendiente' ? 'bg-amber-500/15 text-amber-400' : 'text-zinc-500'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer"
                        >
                            Pendientes
                        </button>
                        <button
                            type="button"
                            @click="filtroEstado = 'aprobada'"
                            :class="filtroEstado === 'aprobada' ? 'bg-emerald-500/15 text-emerald-400' : 'text-zinc-500'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer"
                        >
                            Aprobadas
                        </button>
                        <button
                            type="button"
                            @click="filtroEstado = 'rechazada'"
                            :class="filtroEstado === 'rechazada' ? 'bg-rose-500/15 text-rose-400' : 'text-zinc-500'"
                            class="px-2.5 py-1 rounded-lg font-bold cursor-pointer"
                        >
                            Denegadas
                        </button>
                    </div>
                </div>

                <div
                    v-if="solicitudesFiltradas.length === 0"
                    class="p-10 text-center text-xs text-zinc-500 border border-dashed border-zinc-800 rounded-xl"
                >
                    No hay solicitudes de intercambio para el filtro seleccionado.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in solicitudesFiltradas"
                        :key="item.id"
                        class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                    >
                        <div class="space-y-1 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                    :class="item.estado === 'aprobada'
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : (item.estado === 'rechazada'
                                            ? 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                            : (item.estado === 'abierta_bolsa'
                                                ? 'bg-amber-500/15 text-amber-300 border-amber-500/40'
                                                : (item.estado === 'pendiente_companero'
                                                    ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'
                                                    : 'bg-amber-500/10 text-amber-400 border-amber-500/20')))"
                                >
                                    {{
                                        item.estado === 'abierta_bolsa'
                                            ? 'Vacante en Bolsa'
                                            : (item.estado === 'pendiente_companero'
                                                ? 'Pendiente Compañero'
                                                : (item.estado === 'pendiente_aprobacion'
                                                    ? 'Pendiente Firma Jefatura'
                                                    : item.estado))
                                    }}
                                </span>
                                <span
                                    v-if="item.tipo_solicitud === 'cobertura_vacante'"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/10 border border-amber-500/30 text-amber-300"
                                >
                                    Cobertura Vacante
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-zinc-900 border border-zinc-800 text-zinc-400">
                                    {{ item.colectivo }}
                                </span>
                            </div>

                            <p class="text-white font-semibold mt-1">
                                <span class="text-emerald-400">{{ item.solicitante }}</span>
                                <span class="font-mono text-zinc-400"> ({{ item.fecha_origen }}) </span>
                                <ArrowLeftRight class="inline w-3.5 h-3.5 mx-1 text-indigo-400" />
                                <span class="text-indigo-300">{{ item.destinatario }}</span>
                                <span v-if="item.tipo_solicitud !== 'cobertura_vacante'" class="font-mono text-zinc-400"> ({{ item.fecha_destino }}) </span>
                            </p>

                            <p class="text-[11px] text-zinc-400 italic">"{{ item.motivo }}"</p>

                            <div v-if="item.impacto_equidad" class="flex flex-wrap items-center gap-2 pt-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-zinc-900 border border-zinc-800 text-zinc-300">
                                    Equidad anual: {{ item.impacto_equidad.solicitante_horas_anio }}h
                                    <template v-if="item.impacto_equidad.destinatario_horas_anio !== null">
                                        → {{ item.impacto_equidad.destinatario_horas_anio }}h
                                        ({{ item.impacto_equidad.delta_destinatario >= 0 ? '+' : '' }}{{ item.impacto_equidad.delta_destinatario }}h)
                                    </template>
                                </span>
                                <span
                                    v-if="item.impacto_equidad.supera_48h_semana"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 border border-amber-500/30 text-amber-300"
                                >
                                    Aviso: supera 48h/semana ISO
                                </span>
                            </div>

                            <p v-if="item.revisor" class="text-[10px] font-mono text-zinc-500">
                                Revisado por {{ item.revisor }} ({{ item.resuelto_at }})
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <template v-if="item.puede_postular_vacante">
                                <button
                                    type="button"
                                    @click="postularVacante(item.id)"
                                    class="px-3 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500 text-amber-300 hover:text-zinc-950 border border-amber-500/30 text-xs font-bold flex items-center gap-1 cursor-pointer transition-all"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Cubrir Vacante (Voluntario)
                                </button>
                            </template>

                            <template v-if="item.puede_aceptar_companero">
                                <button
                                    type="button"
                                    @click="responderCompanero(item.id, 'aceptar')"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-500/15 hover:bg-indigo-500 text-indigo-300 hover:text-zinc-950 border border-indigo-500/30 text-xs font-bold flex items-center gap-1 cursor-pointer transition-all"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Aceptar Intercambio
                                </button>
                                <button
                                    type="button"
                                    @click="responderCompanero(item.id, 'rechazar')"
                                    class="px-3 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold flex items-center gap-1 cursor-pointer transition-all"
                                >
                                    <XCircle class="w-3.5 h-3.5" /> Rechazar
                                </button>
                            </template>

                            <template v-if="item.puede_firmar">
                                <button
                                    type="button"
                                    @click="resolverSolicitud(item.id, 'aprobada')"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500 text-emerald-300 hover:text-zinc-950 border border-emerald-500/30 text-xs font-bold flex items-center gap-1 cursor-pointer transition-all"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Aprobar
                                </button>
                                <button
                                    type="button"
                                    @click="resolverSolicitud(item.id, 'rechazada')"
                                    class="px-3 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold flex items-center gap-1 cursor-pointer transition-all"
                                >
                                    <XCircle class="w-3.5 h-3.5" /> Denegar
                                </button>
                            </template>

                            <button
                                v-if="['pendiente', 'pendiente_companero', 'pendiente_aprobacion', 'abierta_bolsa'].includes(item.estado) && (item.puede_cancelar ?? (permisos.es_jefe || item.solicitante_id === usuarioActualId))"
                                type="button"
                                @click="cancelarSolicitud(item.id)"
                                title="Cancelar solicitud"
                                class="p-2 rounded-lg bg-zinc-900 hover:bg-rose-500/15 text-zinc-400 hover:text-rose-400 border border-zinc-800 cursor-pointer"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
