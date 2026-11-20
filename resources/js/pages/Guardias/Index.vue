<script setup lang="ts">
import GuardiasCalendarGrid from '@/components/guardias/GuardiasCalendarGrid.vue';
import GuardiasEquidadTable from '@/components/guardias/GuardiasEquidadTable.vue';
import GuardiasGeneratorPanel from '@/components/guardias/GuardiasGeneratorPanel.vue';
import GuardiasHeaderBar from '@/components/guardias/GuardiasHeaderBar.vue';
import GuardiasReglasModal from '@/components/guardias/GuardiasReglasModal.vue';
import GuardiasSimuladorModal from '@/components/guardias/GuardiasSimuladorModal.vue';
import {
    useGuardiasCalendar,
    type GuardiasPageProps,
} from '@/composables/useGuardiasCalendar';
import { Head } from '@inertiajs/vue3';

const props = defineProps<GuardiasPageProps>();

const {
    pestanaActual,
    filtroMedicoVista,
    alternarFiltroMedico,
    mesFiltro,
    anioFiltro,
    aniosDisponibles,
    navegarMes,
    mesAnterior,
    mesSiguiente,
    limitacionesRecurrentes,
    limitacionesMensuales,
    totalReglasVisibles,
    guardiasFiltradas,
    guardiaOrigen,
    guardiaDestinoPermuta,
    modalPermutaConflictoAbierto,
    cargandoConflictoPermuta,
    diagnosticoPermuta,
    iniciarOEjecutarPermuta,
    evaluarPermutaEnTiempoReal,
    confirmarPermutaVerificada,
    cerrarModalPermutaConflicto,
    cancelarPermuta,
    borrarGuardia,
    eliminarLimitacion,
    nuclearVaciarMes,
    formGenerador,
    simulando,
    modalSimuladorAbierto,
    resultadoSimulacion,
    simularAlgoritmo,
    confirmarGeneracionDesdeSimulador,
    dispararAlgoritmo,
    formLimitacion,
    guardarRegla,
    formManual,
    modalAsignacionAbierto,
    diaSeleccionado,
    cargandoConflictoManual,
    diagnosticoManual,
    abrirModalAsignacion,
    guardarGuardiaDesdeCalendario,
    diasMatriz,
} = useGuardiasCalendar(props, {
    basePath: '/guardias',
    sujetoSingular: 'facultativo',
    mensajeConfirmarVaciar:
        '⚠️ ALERTA MÁXIMA: Esta acción borrará TODAS las guardias (incluidas manuales) de ESTE MES. ¿Continuar?',
    mensajeConfirmarGenerar: (mes, anio) =>
        `Esto calculará el cuadrante óptimo de ${mes}/${anio} respetando las guardias manuales fijadas. ¿Continuar?`,
});
</script>

<template>
    <Head title="Inteligencia de Turnos" />

    <div class="space-y-4 sm:space-y-6 relative max-w-full overflow-hidden">
        <GuardiasHeaderBar
            variante="adjuntos"
            titulo-aviso-motor="Aviso del Motor Clínico"
            titulo-modo-permuta="Modo Permuta Activo"
            :guardia-origen="guardiaOrigen"
            :medicos="medicos"
            etiqueta-todos-filtro="Ver todos los facultativos"
            v-model:filtro-medico-vista="filtroMedicoVista"
            v-model:mes-filtro="mesFiltro"
            v-model:anio-filtro="anioFiltro"
            v-model:pestana-actual="pestanaActual"
            :anios-disponibles="aniosDisponibles"
            :total-reglas-visibles="totalReglasVisibles"
            @cancelar-permuta="cancelarPermuta"
            @navegar-mes="navegarMes"
            @mes-anterior="mesAnterior"
            @mes-siguiente="mesSiguiente"
        >
            <template #generator-panel>
                <GuardiasGeneratorPanel
                    variante="adjuntos"
                    base-path="/guardias"
                    badge-motor="MOTOR CSP v6.0 (3 FASES + MRV + FRONTERA DE MES)"
                    titulo-planificador="Planificador Sanitario de Adjuntos"
                    :etiqueta-plantilla-completa="`Usar plantilla completa (${medicos.length} facultativos)`"
                    etiqueta-selector-medicos="Selecciona los facultativos que entran en el sorteo de este mes:"
                    titulo-parametros="Reglas y Parámetros del Algoritmo:"
                    :es-jefe="Boolean(permisos?.es_jefe)"
                    :medicos="medicos"
                    :mes-filtro="mesFiltro"
                    :anio-filtro="anioFiltro"
                    :form-generador="formGenerador"
                    :simulando="simulando"
                    @vaciar-mes="nuclearVaciarMes"
                    @simular-algoritmo="simularAlgoritmo"
                    @disparar-algoritmo="dispararAlgoritmo"
                />
            </template>
        </GuardiasHeaderBar>

        <GuardiasSimuladorModal
            :abierto="modalSimuladorAbierto"
            variante="adjuntos"
            :resultado="resultadoSimulacion"
            :procesando-guardado="formGenerador.processing"
            @cerrar="modalSimuladorAbierto = false"
            @aplicar-cuadrante="confirmarGeneracionDesdeSimulador"
        />

        <GuardiasEquidadTable
            v-if="pestanaActual === 'cuadrante'"
            variante="adjuntos"
            titulo-auditoria-equidad="Auditoría de Equidad Mensual (17h = 1 pto | 24h = 2 ptos)"
            columna-facultativo="Facultativo"
            :equidad="equidad"
            :guardias-filtradas="guardiasFiltradas"
            :filtro-medico-vista="filtroMedicoVista"
            @alternar-filtro-medico="alternarFiltroMedico"
        />

        <GuardiasCalendarGrid
            v-if="pestanaActual === 'calendario'"
            variante="adjuntos"
            :es-jefe="Boolean(permisos?.es_jefe)"
            :dias-matriz="diasMatriz"
            :medicos="medicos"
            :modal-asignacion-abierto="modalAsignacionAbierto"
            :dia-seleccionado="diaSeleccionado"
            :form-manual="formManual"
            :cargando-conflicto-manual="cargandoConflictoManual"
            :diagnostico-manual="diagnosticoManual"
            :guardia-origen="guardiaOrigen"
            :guardia-destino-permuta="guardiaDestinoPermuta"
            :modal-permuta-conflicto-abierto="modalPermutaConflictoAbierto"
            :cargando-conflicto-permuta="cargandoConflictoPermuta"
            :diagnostico-permuta="diagnosticoPermuta"
            titulo-modal-fijar="Fijar Guardia"
            label-selector-modal="Facultativo Asignado"
            placeholder-selector-modal="Selecciona un facultativo..."
            @abrir-modal-asignacion="abrirModalAsignacion"
            @cerrar-modal-asignacion="modalAsignacionAbierto = false"
            @guardar-guardia-manual="guardarGuardiaDesdeCalendario"
            @iniciar-o-ejecutar-permuta="iniciarOEjecutarPermuta"
            @arrastrar-permuta="evaluarPermutaEnTiempoReal"
            @confirmar-permuta-verificada="confirmarPermutaVerificada"
            @cerrar-modal-permuta-conflicto="cerrarModalPermutaConflicto"
            @borrar-guardia="borrarGuardia"
        />

        <GuardiasReglasModal
            v-if="pestanaActual === 'reglas'"
            variante="adjuntos"
            :es-jefe="Boolean(permisos?.es_jefe)"
            :medicos="medicos"
            :form-limitacion="formLimitacion"
            :limitaciones-recurrentes="limitacionesRecurrentes"
            :limitaciones-mensuales="limitacionesMensuales"
            titulo-formulario="Añadir veto o restricción"
            label-sujeto-implicado="Facultativo implicado"
            placeholder-motivo="Ej: Reducción de jornada, consulta..."
            @guardar-regla="guardarRegla"
            @eliminar-limitacion="eliminarLimitacion"
        />
    </div>
</template>

<style>
.animate-in {
    animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
