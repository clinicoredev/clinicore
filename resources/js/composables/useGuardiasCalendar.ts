import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface GuardiaItem {
    id: number;
    user_id: number;
    facultativo: string;
    fecha: string;
    fecha_formateada: string;
    dia_semana: string;
    tipo: 'diaria_17h' | 'festivo_24h';
    modalidad?: 'presencial' | 'localizada';
    horas?: number;
    horas_efectivas?: number;
    es_festivo_oficial: boolean;
    es_festivo_especial: boolean;
    tipo_badge: string;
    es_finde: boolean;
    is_manual: boolean;
    observaciones?: string | null;
}

export interface LimitacionItem {
    id: number;
    user_id: number;
    medico: string;
    regla: string;
    tipo_raw: 'dia_semana' | 'fecha_concreta' | 'periodo';
    valor: string;
    motivo: string;
}

export interface AusenciaMesItem {
    id: number;
    user_id: number;
    medico: string;
    inicio: string;
    fin: string;
    tipo_raw: string;
    bloquea_guardias: boolean;
    motivo: string;
}

export interface FestivoItem {
    id: number;
    fecha: string;
    es_especial: boolean;
    descripcion: string;
}

export interface MedicoItem {
    id: number;
    name: string;
    email?: string;
}

export interface EquidadItem {
    id: number;
    nombre: string;
    porcentaje_jornada?: number;
    exento_guardias?: boolean;
    dias_disponibles: number;
    diarias: number;
    findes: number;
    festivos: number;
    festivos_anual: number;
    especiales_anual: number;
    totales: number;
    horas_totales?: number;
    excede_tope_legal?: boolean;
    puntos: number;
}

export interface SalienteLunesItem {
    id: string;
    user_id: number;
    medico: string;
    fecha: string;
    fecha_sabado: string;
    motivo: string;
}

export interface VetoCeldaItem {
    id: number | string;
    medico: string;
    motivo: string;
    es_ausencia?: boolean;
    es_rotacion_guardias?: boolean;
    es_saliente_lunes?: boolean;
}

export interface CeldaCalendario {
    esRelleno: boolean;
    numero?: number;
    es_finde?: boolean;
    es_finde_natural?: boolean;
    es_festivo?: boolean;
    es_festivo_especial?: boolean;
    nombre_festivo?: string;
    fecha_vence?: string;
    tiene_guardia?: boolean;
    guardias_dia?: GuardiaItem[];
    vetos_dia?: VetoCeldaItem[];
}

export interface AlertaRelajacionItem {
    fecha: string;
    fecha_formateada: string;
    user_id: number;
    facultativo: string;
    motivo: string;
}

export interface AlertaTopeLegalItem {
    user_id: number;
    facultativo: string;
    semana: string;
    horas_semana: number;
    motivo: string;
}

export interface SimulacionResultado {
    ok: boolean;
    error: string | null;
    resumen: {
        mes: number;
        anio: number;
        colectivo: string;
        turnos_nuevos: number;
        manuales_respetados: number;
        total_alertas_relajacion: number;
        total_alertas_tope_legal?: number;
    };
    alertas_relajacion: AlertaRelajacionItem[];
    alertas_tope_legal?: AlertaTopeLegalItem[];
    guardias_simuladas: GuardiaItem[];
    equidad_simulada: EquidadItem[];
}

export interface ConflictoDetalleItem {
    user_id: number;
    facultativo: string;
    fecha: string;
    fecha_formateada: string;
    tipo: string;
    severidad: 'bloqueante' | 'advertencia';
    mensaje: string;
}

export interface DiagnosticoConflictoResultado {
    valido: boolean;
    bloqueante: boolean;
    forzable: boolean;
    conflictos: ConflictoDetalleItem[];
    mensaje_resumen: string | null;
}

export interface GuardiasPageProps {
    guardias: GuardiaItem[];
    limitaciones: LimitacionItem[];
    ausencias_mes: AusenciaMesItem[];
    salientes_lunes?: SalienteLunesItem[];
    festivos: FestivoItem[];
    medicos: MedicoItem[];
    equidad: EquidadItem[];
    permisos: { es_jefe: boolean };
    mes_actual: number;
    anio_actual: number;
}

export interface UseGuardiasCalendarOptions {
    basePath: string;
    sujetoSingular: string;
    mensajeConfirmarVaciar: string;
    mensajeConfirmarGenerar: (mes: number, anio: number) => string;
}

export function useGuardiasCalendar(
    props: GuardiasPageProps,
    options: UseGuardiasCalendarOptions,
) {
    const pestanaActual = ref<'cuadrante' | 'calendario' | 'reglas'>('cuadrante');
    const filtroMedicoVista = ref<'todos' | number>('todos');

    const mesFiltro = ref<number>(props.mes_actual);
    const anioFiltro = ref<number>(props.anio_actual);

    const aniosDisponibles = computed<number[]>(() => {
        const actual = new Date().getFullYear();
        return Array.from({ length: 6 }, (_, i) => actual - 1 + i);
    });

    const navegarMes = () => {
        router.get(
            options.basePath,
            { mes: mesFiltro.value, anio: anioFiltro.value },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const mesAnterior = () => {
        if (mesFiltro.value === 1) {
            mesFiltro.value = 12;
            anioFiltro.value--;
        } else {
            mesFiltro.value--;
        }
        navegarMes();
    };

    const mesSiguiente = () => {
        if (mesFiltro.value === 12) {
            mesFiltro.value = 1;
            anioFiltro.value++;
        } else {
            mesFiltro.value++;
        }
        navegarMes();
    };

    const limitacionesRecurrentes = computed(() =>
        (props.limitaciones ?? []).filter((l) => l.tipo_raw === 'dia_semana'),
    );
    const limitacionesMensuales = computed(() =>
        (props.limitaciones ?? []).filter((l) => l.tipo_raw !== 'dia_semana'),
    );
    const totalReglasVisibles = computed(
        () => limitacionesRecurrentes.value.length + limitacionesMensuales.value.length,
    );

    const guardiasFiltradas = computed(() => {
        if (filtroMedicoVista.value === 'todos') {
            return props.guardias ?? [];
        }
        return (props.guardias ?? []).filter(
            (g) => g.user_id === filtroMedicoVista.value,
        );
    });

    const obtenerCsrfToken = (): string => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content') || '';
        }
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    const guardiaOrigen = ref<GuardiaItem | null>(null);
    const guardiaDestinoPermuta = ref<GuardiaItem | null>(null);
    const modalPermutaConflictoAbierto = ref(false);
    const cargandoConflictoPermuta = ref(false);
    const diagnosticoPermuta = ref<DiagnosticoConflictoResultado | null>(null);

    const evaluarPermutaEnTiempoReal = async (origen: GuardiaItem, destino: GuardiaItem) => {
        guardiaOrigen.value = origen;
        guardiaDestinoPermuta.value = destino;
        diagnosticoPermuta.value = null;
        cargandoConflictoPermuta.value = true;
        modalPermutaConflictoAbierto.value = true;

        try {
            const resp = await fetch(`${options.basePath}/verificar-conflicto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': obtenerCsrfToken(),
                },
                body: JSON.stringify({
                    modo: 'permuta',
                    origen_id: origen.id,
                    destino_id: destino.id,
                    distancia_minima_dias: formGenerador.distancia_minima_dias ?? 1,
                }),
            });
            if (resp.ok) {
                diagnosticoPermuta.value = (await resp.json()) as DiagnosticoConflictoResultado;
            }
        } catch {
            // En caso de fallo de red se permitirá continuar hacia validación backend
        } finally {
            cargandoConflictoPermuta.value = false;
        }
    };

    const iniciarOEjecutarPermuta = (celda: GuardiaItem) => {
        if (!guardiaOrigen.value) {
            guardiaOrigen.value = celda;
            return;
        }
        if (guardiaOrigen.value.id === celda.id) {
            guardiaOrigen.value = null;
            return;
        }
        void evaluarPermutaEnTiempoReal(guardiaOrigen.value, celda);
    };

    const confirmarPermutaVerificada = () => {
        if (!guardiaOrigen.value || !guardiaDestinoPermuta.value) {
            return;
        }
        if (diagnosticoPermuta.value?.bloqueante) {
            return;
        }
        router.post(
            `${options.basePath}/permutar`,
            {
                origen_id: guardiaOrigen.value.id,
                destino_id: guardiaDestinoPermuta.value.id,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    guardiaOrigen.value = null;
                    guardiaDestinoPermuta.value = null;
                    modalPermutaConflictoAbierto.value = false;
                    diagnosticoPermuta.value = null;
                },
            },
        );
    };

    const cerrarModalPermutaConflicto = () => {
        modalPermutaConflictoAbierto.value = false;
        guardiaDestinoPermuta.value = null;
        diagnosticoPermuta.value = null;
    };

    const cancelarPermuta = () => {
        guardiaOrigen.value = null;
        cerrarModalPermutaConflicto();
    };

    const borrarGuardia = (id: number) => {
        if (
            confirm(
                `¿Seguro que deseas eliminar este turno específico${options.sujetoSingular === 'residente' ? ' de residente' : ''}?`,
            )
        ) {
            router.delete(`${options.basePath}/${id}`, { preserveScroll: true });
        }
    };

    const eliminarLimitacion = (id: number) => {
        router.delete(`${options.basePath}/limitaciones/${id}`, {
            preserveScroll: true,
        });
    };

    const nuclearVaciarMes = () => {
        if (confirm(options.mensajeConfirmarVaciar)) {
            router.delete(
                `${options.basePath}/vaciar-mes?mes=${mesFiltro.value}&anio=${anioFiltro.value}`,
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const formGenerador = useForm({
        mes: mesFiltro.value,
        anio: anioFiltro.value,
        personas_por_dia: 1,
        usar_plantilla_completa: true,
        medicos_incluidos: (props.medicos ?? []).map((m) => m.id),
        respetar_salientes: true,
        distancia_minima_dias: 1,
        proteger_vispera_vacaciones: true,
        max_guardias_mes: 0,
        max_findes_mes: 0,
        max_diarias_mes: 0,
        prorratear_ausencias: true,
        usar_memoria_anual: true,
        agrupacion: 'ninguna',
        modalidad: 'presencial' as 'presencial' | 'localizada',
    });

    watch(
        () => formGenerador.usar_plantilla_completa,
        (nuevoValor) => {
            if (nuevoValor) {
                formGenerador.medicos_incluidos = (props.medicos ?? []).map(
                    (m) => m.id,
                );
            }
        },
    );

    const simulando = ref(false);
    const modalSimuladorAbierto = ref(false);
    const resultadoSimulacion = ref<SimulacionResultado | null>(null);

    const simularAlgoritmo = async () => {
        formGenerador.mes = mesFiltro.value;
        formGenerador.anio = anioFiltro.value;

        if (
            !formGenerador.usar_plantilla_completa &&
            formGenerador.medicos_incluidos.length < formGenerador.personas_por_dia
        ) {
            alert(
                `Debes incluir al menos ${formGenerador.personas_por_dia} ${options.sujetoSingular}(s) en el algoritmo.`,
            );
            return;
        }

        simulando.value = true;
        try {
            const resp = await fetch(`${options.basePath}/simular`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': obtenerCsrfToken(),
                },
                body: JSON.stringify(formGenerador.data()),
            });
            if (resp.ok) {
                resultadoSimulacion.value = (await resp.json()) as SimulacionResultado;
                modalSimuladorAbierto.value = true;
            }
        } finally {
            simulando.value = false;
        }
    };

    const confirmarGeneracionDesdeSimulador = () => {
        formGenerador.mes = mesFiltro.value;
        formGenerador.anio = anioFiltro.value;
        formGenerador.post(`${options.basePath}/generar`, {
            preserveScroll: true,
            onSuccess: () => {
                modalSimuladorAbierto.value = false;
                resultadoSimulacion.value = null;
            },
        });
    };

    const dispararAlgoritmo = () => {
        formGenerador.mes = mesFiltro.value;
        formGenerador.anio = anioFiltro.value;

        if (
            !formGenerador.usar_plantilla_completa &&
            formGenerador.medicos_incluidos.length < formGenerador.personas_por_dia
        ) {
            alert(
                `Debes incluir al menos ${formGenerador.personas_por_dia} ${options.sujetoSingular}(s) en el algoritmo.`,
            );
            return;
        }

        if (
            confirm(
                options.mensajeConfirmarGenerar(mesFiltro.value, anioFiltro.value),
            )
        ) {
            formGenerador.post(`${options.basePath}/generar`, {
                preserveScroll: true,
            });
        }
    };

    const formLimitacion = useForm({
        user_id: props.medicos?.[0]?.id ?? '',
        tipo: 'dia_semana',
        valor: '1',
        fecha_inicio: '',
        fecha_fin: '',
        motivo: '',
    });

    const guardarRegla = () => {
        formLimitacion.post(`${options.basePath}/limitaciones`, {
            preserveScroll: true,
            onSuccess: () =>
                formLimitacion.reset('motivo', 'fecha_inicio', 'fecha_fin'),
        });
    };

    const formManual = useForm({
        user_id: '' as number | string,
        fecha: '',
        tipo: 'diaria_17h',
        modalidad: 'presencial',
        forzar: false,
    });

    const modalAsignacionAbierto = ref(false);
    const diaSeleccionado = ref<CeldaCalendario | null>(null);
    const cargandoConflictoManual = ref(false);
    const diagnosticoManual = ref<DiagnosticoConflictoResultado | null>(null);

    const verificarConflictoAsignacionManual = async () => {
        if (!modalAsignacionAbierto.value || !formManual.user_id || !formManual.fecha) {
            diagnosticoManual.value = null;
            return;
        }

        cargandoConflictoManual.value = true;
        try {
            const resp = await fetch(`${options.basePath}/verificar-conflicto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': obtenerCsrfToken(),
                },
                body: JSON.stringify({
                    modo: 'asignacion',
                    user_id: Number(formManual.user_id),
                    fecha: formManual.fecha,
                    distancia_minima_dias: formGenerador.distancia_minima_dias ?? 1,
                }),
            });
            if (resp.ok) {
                diagnosticoManual.value = (await resp.json()) as DiagnosticoConflictoResultado;
            }
        } catch {
            diagnosticoManual.value = null;
        } finally {
            cargandoConflictoManual.value = false;
        }
    };

    watch(
        () => [formManual.user_id, formManual.fecha, modalAsignacionAbierto.value],
        () => {
            void verificarConflictoAsignacionManual();
        },
    );

    const abrirModalAsignacion = (celda: CeldaCalendario) => {
        if (celda.esRelleno || !props.permisos?.es_jefe) {
            return;
        }
        diaSeleccionado.value = celda;
        diagnosticoManual.value = null;

        formManual.fecha = celda.fecha_vence ?? '';
        formManual.user_id = '';
        formManual.forzar = false;
        formManual.tipo =
            celda.es_festivo || celda.es_finde_natural
                ? 'festivo_24h'
                : 'diaria_17h';

        modalAsignacionAbierto.value = true;
    };

    const guardarGuardiaDesdeCalendario = () => {
        formManual.post(`${options.basePath}/manual`, {
            preserveScroll: true,
            onSuccess: () => {
                modalAsignacionAbierto.value = false;
                formManual.reset('user_id', 'fecha', 'forzar');
                diaSeleccionado.value = null;
            },
        });
    };

    const alternarFiltroMedico = (medicoId: number) => {
        filtroMedicoVista.value =
            filtroMedicoVista.value === medicoId ? 'todos' : medicoId;
    };

    const diasMatriz = computed<CeldaCalendario[]>(() => {
        const year = anioFiltro.value;
        const month = mesFiltro.value;

        const primerDia = new Date(year, month - 1, 1);
        let desfaseInicial = primerDia.getDay();
        desfaseInicial = desfaseInicial === 0 ? 6 : desfaseInicial - 1;

        const totalDias = new Date(year, month, 0).getDate();
        const matrizCompleta: CeldaCalendario[] = [];

        for (let i = 0; i < desfaseInicial; i++) {
            matrizCompleta.push({ esRelleno: true });
        }

        const mapaGuardias: Record<number, GuardiaItem[]> = {};
        if (props.guardias) {
            props.guardias.forEach((g) => {
                if (
                    filtroMedicoVista.value !== 'todos' &&
                    g.user_id !== filtroMedicoVista.value
                ) {
                    return;
                }
                const partes = g.fecha_formateada
                    ? g.fecha_formateada.split('/')
                    : [];
                if (partes.length > 0) {
                    const diaNum = parseInt(partes[0], 10);
                    if (!mapaGuardias[diaNum]) {
                        mapaGuardias[diaNum] = [];
                    }
                    mapaGuardias[diaNum].push(g);
                }
            });
        }

        for (let d = 1; d <= totalDias; d++) {
            const fechaObj = new Date(year, month - 1, d);
            const dayOfWeek = fechaObj.getDay();

            const mm = String(month).padStart(2, '0');
            const dd = String(d).padStart(2, '0');
            const fechaString = `${year}-${mm}-${dd}`;

            const festivoData = props.festivos?.find(
                (f) => f.fecha === fechaString,
            );
            const esFestivo = !!festivoData;

            const esFindeNatural = dayOfWeek === 0 || dayOfWeek === 6;
            const esFindeVirtual = esFindeNatural || esFestivo;

            const vetosDelDia: VetoCeldaItem[] = [];
            (props.limitaciones ?? []).forEach((l) => {
                if (
                    filtroMedicoVista.value !== 'todos' &&
                    l.user_id !== filtroMedicoVista.value
                ) {
                    return;
                }
                if (l.tipo_raw === 'dia_semana') {
                    const isoDay = dayOfWeek === 0 ? 7 : dayOfWeek;
                    if (parseInt(l.valor, 10) === isoDay) {
                        vetosDelDia.push(l);
                    }
                } else if (l.tipo_raw === 'fecha_concreta') {
                    if (l.valor === fechaString) {
                        vetosDelDia.push(l);
                    }
                } else if (l.tipo_raw === 'periodo') {
                    const parts = l.valor.split(',');
                    if (
                        parts.length === 2 &&
                        fechaString >= parts[0] &&
                        fechaString <= parts[1]
                    ) {
                        vetosDelDia.push(l);
                    }
                }
            });

            if (props.ausencias_mes) {
                props.ausencias_mes.forEach((a) => {
                    if (
                        filtroMedicoVista.value !== 'todos' &&
                        a.user_id !== filtroMedicoVista.value
                    ) {
                        return;
                    }
                    if (fechaString >= a.inicio && fechaString <= a.fin) {
                        vetosDelDia.push({
                            id: 'aus_' + a.id,
                            medico: a.medico,
                            motivo: a.motivo,
                            es_ausencia: a.bloquea_guardias,
                            es_rotacion_guardias: !a.bloquea_guardias,
                        });
                    }
                });
            }

            if (dayOfWeek === 1 && props.salientes_lunes) {
                props.salientes_lunes.forEach((sl) => {
                    if (
                        filtroMedicoVista.value !== 'todos' &&
                        sl.user_id !== filtroMedicoVista.value
                    ) {
                        return;
                    }
                    if (sl.fecha === fechaString) {
                        vetosDelDia.push({
                            id: sl.id,
                            medico: sl.medico,
                            motivo: sl.motivo,
                            es_saliente_lunes: true,
                        });
                    }
                });
            }

            matrizCompleta.push({
                esRelleno: false,
                numero: d,
                es_finde: esFindeVirtual,
                es_finde_natural: esFindeNatural,
                es_festivo: esFestivo,
                es_festivo_especial: festivoData ? festivoData.es_especial : false,
                nombre_festivo: festivoData ? festivoData.descripcion : '',
                fecha_vence: fechaString,
                tiene_guardia: !!(mapaGuardias[d] && mapaGuardias[d].length > 0),
                guardias_dia: mapaGuardias[d] || [],
                vetos_dia: vetosDelDia,
            });
        }

        return matrizCompleta;
    });

    return {
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
    };
}
