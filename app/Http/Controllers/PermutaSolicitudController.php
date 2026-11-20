<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Guardia;
use App\Models\SolicitudPermuta;
use App\Models\User;
use App\Notifications\PermutaPropuestaNotification;
use App\Notifications\PermutaResueltaNotification;
use App\Notifications\TurnoVacanteNotification;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PermutaSolicitudController extends Controller
{
    private const ESTADOS_ACTIVOS = ['pendiente', 'pendiente_companero', 'pendiente_aprobacion', 'abierta_bolsa'];

    public function __construct(
        private readonly GuardiaGeneratorService $generatorService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        $esJefe = $usuario->hasRole('Jefe de Servicio') || $usuario->hasRole('SuperAdmin');
        $esTutor = $usuario->hasRole('Tutor de Residentes');
        $esResidente = $usuario->hasAnyRole(['Residente', 'Residente Mayor']);
        $colectivoUsuario = $esResidente
            ? GuardiaGeneratorService::COLECTIVO_RESIDENTES
            : GuardiaGeneratorService::COLECTIVO_ADJUNTOS;

        $query = SolicitudPermuta::where('especialidad_id', $especialidadId)
            ->with([
                'guardiaOrigen.facultativo:id,name',
                'guardiaDestino.facultativo:id,name',
                'solicitante:id,name',
                'destinatario:id,name',
                'revisor:id,name',
            ]);

        if ($esJefe) {
            // El Jefe de Servicio supervisa todas las solicitudes de su especialidad
        } elseif ($esTutor) {
            $query->where(function ($q) use ($usuario, $colectivoUsuario) {
                $q->where('colectivo', GuardiaGeneratorService::COLECTIVO_RESIDENTES)
                    ->orWhere('solicitante_id', $usuario->id)
                    ->orWhere('destinatario_id', $usuario->id)
                    ->orWhere(fn($sub) => $sub->where('estado', 'abierta_bolsa')->where('colectivo', $colectivoUsuario));
            });
        } else {
            $query->where(function ($q) use ($usuario, $colectivoUsuario) {
                $q->where('solicitante_id', $usuario->id)
                    ->orWhere('destinatario_id', $usuario->id)
                    ->orWhere(fn($sub) => $sub->where('estado', 'abierta_bolsa')->where('colectivo', $colectivoUsuario));
            });
        }

        $solicitudes = $query->latest()->get()->map(function (SolicitudPermuta $s) use ($usuario, $esJefe, $esTutor, $colectivoUsuario): array {
            $esVacante = ($s->tipo_solicitud ?? 'permuta') === 'cobertura_vacante' || $s->guardia_destino_id === null;
            $fechaOrigen = $s->guardiaOrigen?->fecha ? Carbon::parse($s->guardiaOrigen->fecha)->format('d/m/Y') : 'Eliminada';
            $fechaDestino = $esVacante
                ? 'Cobertura sin devolución'
                : ($s->guardiaDestino?->fecha ? Carbon::parse($s->guardiaDestino->fecha)->format('d/m/Y') : 'Eliminada');

            $puedeAceptarCompanero = !$esVacante
                && $s->estado === 'pendiente_companero'
                && (int) $s->destinatario_id === (int) $usuario->id;

            $puedePostularVacante = $esVacante
                && $s->estado === 'abierta_bolsa'
                && $s->colectivo === $colectivoUsuario
                && (int) $s->solicitante_id !== (int) $usuario->id;

            $puedeFirmar = in_array($s->estado, ['pendiente', 'pendiente_aprobacion'], true) && (
                $esJefe || ($esTutor && $s->colectivo === GuardiaGeneratorService::COLECTIVO_RESIDENTES)
            );

            $puedeCancelar = in_array($s->estado, self::ESTADOS_ACTIVOS, true)
                && ($esJefe || (int) $s->solicitante_id === (int) $usuario->id);

            return [
                'id' => $s->id,
                'tipo_solicitud' => $esVacante ? 'cobertura_vacante' : 'permuta',
                'guardia_origen_id' => $s->guardia_origen_id,
                'guardia_destino_id' => $s->guardia_destino_id,
                'solicitante_id' => $s->solicitante_id,
                'destinatario_id' => $s->destinatario_id,
                'solicitante' => $s->solicitante?->name ?? 'Usuario eliminado',
                'destinatario' => $s->destinatario?->name ?? ($esVacante ? 'Bolsa Abierta (Sin voluntario)' : 'Usuario eliminado'),
                'fecha_origen' => $fechaOrigen,
                'fecha_destino' => $fechaDestino,
                'colectivo' => $s->colectivo,
                'estado' => $s->estado,
                'motivo' => $s->motivo ?? ($esVacante ? 'Cobertura por baja / vacante sobrevenida' : 'Intercambio de turno acordado'),
                'aceptado_destinatario_at' => $s->aceptado_destinatario_at ? $s->aceptado_destinatario_at->format('d/m/Y H:i') : null,
                'revisor' => $s->revisor?->name ?? null,
                'resuelto_at' => $s->resuelto_at ? $s->resuelto_at->format('d/m/Y H:i') : null,
                'puede_aceptar_companero' => $puedeAceptarCompanero,
                'puede_postular_vacante' => $puedePostularVacante,
                'puede_firmar' => $puedeFirmar,
                'puede_cancelar' => $puedeCancelar,
            ];
        });

        $hoy = Carbon::today()->toDateString();
        $mapaFestivos = $this->generatorService->obtenerMapaFestivos($especialidadId);

        $misGuardias = Guardia::where('especialidad_id', $especialidadId)
            ->where('user_id', $usuario->id)
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->get()
            ->map(function (Guardia $g) use ($mapaFestivos): array {
                $info = $this->generatorService->clasificarTipologiaGuardia($g, $mapaFestivos);
                return [
                    'id' => $g->id,
                    'fecha' => $info['fecha_str'],
                    'fecha_formateada' => Carbon::parse($g->fecha)->format('d/m/Y'),
                    'etiqueta' => $info['etiqueta'],
                ];
            });

        $idsCompanerosColectivo = $this->generatorService
            ->queryMedicosColectivo($especialidadId, $colectivoUsuario)
            ->where('id', '!=', $usuario->id)
            ->pluck('id')
            ->toArray();

        $guardiasCompaneros = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsCompanerosColectivo)
            ->whereDate('fecha', '>=', $hoy)
            ->with('facultativo:id,name')
            ->orderBy('fecha')
            ->get()
            ->map(function (Guardia $g) use ($mapaFestivos): array {
                $info = $this->generatorService->clasificarTipologiaGuardia($g, $mapaFestivos);
                return [
                    'id' => $g->id,
                    'user_id' => $g->user_id,
                    'facultativo' => $g->facultativo?->name ?? 'Facultativo',
                    'fecha' => $info['fecha_str'],
                    'fecha_formateada' => Carbon::parse($g->fecha)->format('d/m/Y'),
                    'etiqueta' => $info['etiqueta'],
                ];
            });

        return Inertia::render('Permutas/Index', [
            'solicitudes' => $solicitudes,
            'mis_guardias' => $misGuardias,
            'guardias_companeros' => $guardiasCompaneros,
            'colectivo_usuario' => $colectivoUsuario,
            'permisos' => [
                'es_jefe' => $esJefe,
                'es_tutor' => $esTutor,
                'es_revisor' => $esJefe || $esTutor,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        $validated = $request->validate([
            'guardia_origen_id' => ['required', 'integer', 'exists:guardias,id'],
            'guardia_destino_id' => ['required', 'integer', 'exists:guardias,id', 'different:guardia_origen_id'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $guardiaOrigen = Guardia::withoutGlobalScopes()->with('facultativo.roles')->findOrFail((int) $validated['guardia_origen_id']);
        $guardiaDestino = Guardia::withoutGlobalScopes()->with('facultativo.roles')->findOrFail((int) $validated['guardia_destino_id']);

        if ((int) $guardiaOrigen->especialidad_id !== $especialidadId || (int) $guardiaDestino->especialidad_id !== $especialidadId) {
            abort(403, 'Acceso denegado: las guardias no pertenecen a tu servicio.');
        }

        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');

        if (!$esJefe && !$esTutor && (int) $guardiaOrigen->user_id !== (int) $usuario->id) {
            abort(403, 'Solo puedes proponer intercambios sobre tus propios turnos.');
        }

        // F5-01: Bloqueo de fechas pasadas
        $hoy = Carbon::today();
        if (
            Carbon::parse($guardiaOrigen->fecha)->startOfDay()->lt($hoy)
            || Carbon::parse($guardiaDestino->fecha)->startOfDay()->lt($hoy)
        ) {
            return back()->withErrors([
                'conflicto' => 'No se pueden solicitar permutas sobre guardias en fechas pasadas.',
            ]);
        }

        $origenEsResidente = $guardiaOrigen->facultativo?->hasAnyRole(['Residente', 'Residente Mayor']) ?? false;
        $destinoEsResidente = $guardiaDestino->facultativo?->hasAnyRole(['Residente', 'Residente Mayor']) ?? false;

        if ($origenEsResidente !== $destinoEsResidente) {
            return back()->withErrors(['conflicto' => 'No se pueden intercambiar guardias entre colectivos distintos (Adjuntos vs Residentes).']);
        }

        $colectivo = $origenEsResidente
            ? GuardiaGeneratorService::COLECTIVO_RESIDENTES
            : GuardiaGeneratorService::COLECTIVO_ADJUNTOS;

        if ($esTutor && !$esJefe && (int) $guardiaOrigen->user_id !== (int) $usuario->id && !$origenEsResidente) {
            abort(403, 'Como Tutor solo puedes tramitar permutas de residentes.');
        }

        // F5-01: Prevención de solicitudes duplicadas pendientes sobre los mismos turnos
        $duplicada = SolicitudPermuta::where('especialidad_id', $especialidadId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where(function ($q) use ($guardiaOrigen, $guardiaDestino) {
                $idsTurnos = [(int) $guardiaOrigen->id, (int) $guardiaDestino->id];
                $q->whereIn('guardia_origen_id', $idsTurnos)
                    ->orWhereIn('guardia_destino_id', $idsTurnos);
            })
            ->exists();

        if ($duplicada) {
            return back()->withErrors([
                'conflicto' => 'Ya existe una solicitud de permuta pendiente que involucra uno de estos turnos.',
            ]);
        }

        $diagnostico = $this->generatorService->evaluarConflictosEnTiempoReal($especialidadId, $colectivo, [
            'modo' => 'permuta',
            'origen_id' => (int) $guardiaOrigen->id,
            'destino_id' => (int) $guardiaDestino->id,
            'distancia_minima_dias' => 1,
        ]);

        if ($diagnostico['bloqueante']) {
            return back()->withErrors([
                'conflicto' => 'Solicitud rechazada por conflicto clínico: ' . ($diagnostico['mensaje_resumen'] ?? 'Incumple salientes o ausencias.'),
            ]);
        }

        $solicitud = SolicitudPermuta::create([
            'especialidad_id' => $especialidadId,
            'guardia_origen_id' => $guardiaOrigen->id,
            'guardia_destino_id' => $guardiaDestino->id,
            'solicitante_id' => $guardiaOrigen->user_id,
            'destinatario_id' => $guardiaDestino->user_id,
            'colectivo' => $colectivo,
            'estado' => 'pendiente_companero',
            'motivo' => $validated['motivo'] ?? null,
        ]);

        // F5-04: Notificar al compañero destinatario
        if ($guardiaDestino->facultativo) {
            $guardiaDestino->facultativo->notify(new PermutaPropuestaNotification($solicitud, 'companero'));
        }

        return back()->with('success', 'Solicitud registrada. Pendiente de aceptación por tu compañero antes de pasar a firma.');
    }

    /**
     * F6-05: Publica un turno descubierto o por baja sobrevenida en la bolsa de vacantes del colectivo.
     */
    public function publicarVacante(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        $validated = $request->validate([
            'guardia_origen_id' => ['required', 'integer', 'exists:guardias,id'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $guardiaOrigen = Guardia::withoutGlobalScopes()->with('facultativo.roles')->findOrFail((int) $validated['guardia_origen_id']);

        if ((int) $guardiaOrigen->especialidad_id !== $especialidadId) {
            abort(403, 'Acceso denegado: la guardia no pertenece a tu servicio.');
        }

        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');
        $origenEsResidente = $guardiaOrigen->facultativo?->hasAnyRole(['Residente', 'Residente Mayor']) ?? false;

        if (!$esJefe && !$esTutor && (int) $guardiaOrigen->user_id !== (int) $usuario->id) {
            abort(403, 'Solo puedes publicar en la bolsa tus propios turnos.');
        }

        if ($esTutor && !$esJefe && (int) $guardiaOrigen->user_id !== (int) $usuario->id && !$origenEsResidente) {
            abort(403, 'Como Tutor solo puedes publicar vacantes de residentes.');
        }

        if (Carbon::parse($guardiaOrigen->fecha)->startOfDay()->lt(Carbon::today())) {
            return back()->withErrors([
                'conflicto' => 'No se pueden publicar en la bolsa guardias en fechas pasadas.',
            ]);
        }

        $duplicada = SolicitudPermuta::where('especialidad_id', $especialidadId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where(function ($q) use ($guardiaOrigen) {
                $q->where('guardia_origen_id', $guardiaOrigen->id)
                    ->orWhere('guardia_destino_id', $guardiaOrigen->id);
            })
            ->exists();

        if ($duplicada) {
            return back()->withErrors([
                'conflicto' => 'Ya existe una solicitud activa o vacante abierta para este turno.',
            ]);
        }

        $colectivo = $origenEsResidente
            ? GuardiaGeneratorService::COLECTIVO_RESIDENTES
            : GuardiaGeneratorService::COLECTIVO_ADJUNTOS;

        $solicitud = SolicitudPermuta::create([
            'especialidad_id' => $especialidadId,
            'tipo_solicitud' => 'cobertura_vacante',
            'guardia_origen_id' => $guardiaOrigen->id,
            'guardia_destino_id' => null,
            'solicitante_id' => $guardiaOrigen->user_id,
            'destinatario_id' => null,
            'colectivo' => $colectivo,
            'estado' => 'abierta_bolsa',
            'motivo' => $validated['motivo'] ?: 'Cobertura por baja sobrevenida / turno vacante',
        ]);

        $solicitud->load(['solicitante:id,name', 'guardiaOrigen']);

        $voluntariosPotenciales = $this->generatorService
            ->queryMedicosColectivo($especialidadId, $colectivo)
            ->where('id', '!=', (int) $guardiaOrigen->user_id)
            ->where(function ($q) {
                $q->whereNull('exento_guardias')->orWhere('exento_guardias', false);
            })
            ->get();

        foreach ($voluntariosPotenciales as $voluntario) {
            $voluntario->notify(new TurnoVacanteNotification($solicitud));
        }

        return back()->with('success', 'Turno publicado en la bolsa de cobertura. Se ha notificado a los compañeros del colectivo.');
    }

    /**
     * F6-05: Un voluntario del mismo colectivo se postula para cubrir una guardia vacante,
     * validando ausencias, limitaciones y descansos de saliente (incluyendo sábado -> lunes).
     */
    public function postularVacante(Request $request, SolicitudPermuta $permuta): RedirectResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        if ((int) $permuta->especialidad_id !== $especialidadId) {
            abort(403);
        }

        if (($permuta->tipo_solicitud ?? 'permuta') !== 'cobertura_vacante' || $permuta->estado !== 'abierta_bolsa') {
            return back()->withErrors(['conflicto' => 'Esta vacante ya no está abierta para postulación.']);
        }

        $colectivo = (string) $permuta->colectivo;
        $this->generatorService->validarFacultativoEnColectivo((int) $usuario->id, $especialidadId, $colectivo);

        if ((int) $permuta->solicitante_id === (int) $usuario->id) {
            return back()->withErrors(['conflicto' => 'No puedes postularte para cubrir tu propio turno vacante.']);
        }

        $guardiaOrigen = Guardia::withoutGlobalScopes()->findOrFail((int) $permuta->guardia_origen_id);

        if ($conflicto = $this->generatorService->checkConflict((int) $usuario->id, $guardiaOrigen->fecha, false, $colectivo)) {
            return back()->withErrors(['conflicto' => 'Postulación denegada por conflicto clínico: ' . $conflicto]);
        }

        if ($colision = $this->generatorService->checkPermutaColisionOSaliente((int) $usuario->id, $guardiaOrigen->fecha, (int) $guardiaOrigen->id, 1)) {
            return back()->withErrors(['conflicto' => 'Postulación denegada por descanso de saliente: ' . $colision]);
        }

        $permuta->update([
            'destinatario_id' => $usuario->id,
            'aceptado_destinatario_at' => now(),
            'estado' => 'pendiente_aprobacion',
        ]);

        $permuta->load('solicitante:id,name');

        $revisoresQuery = User::where('especialidad_id', $especialidadId)
            ->whereHas('roles', function ($q) use ($colectivo) {
                $rolesRevisor = $colectivo === GuardiaGeneratorService::COLECTIVO_RESIDENTES
                    ? ['Jefe de Servicio', 'Tutor de Residentes']
                    : ['Jefe de Servicio'];
                $q->whereIn('name', $rolesRevisor);
            });

        foreach ($revisoresQuery->get() as $revisor) {
            $revisor->notify(new PermutaPropuestaNotification($permuta, 'aprobacion'));
        }

        return back()->with('success', 'Te has postulado como voluntario para cubrir la vacante. Pendiente de firma de Jefatura/Tutoría.');
    }

    /**
     * F5-01: Paso 1 de doble validación — El compañero destinatario acepta o rechaza la propuesta de permuta.
     */
    public function responderCompanero(Request $request, SolicitudPermuta $permuta): RedirectResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        if ((int) $permuta->especialidad_id !== $especialidadId) {
            abort(403);
        }

        $esJefe = $usuario->hasRole('Jefe de Servicio');
        if (!$esJefe && (int) $permuta->destinatario_id !== (int) $usuario->id) {
            abort(403, 'Solo el compañero destinatario puede aceptar o rechazar esta solicitud.');
        }

        if ($permuta->estado !== 'pendiente_companero') {
            return back()->withErrors(['conflicto' => 'Esta solicitud ya no está pendiente de confirmación del compañero.']);
        }

        $validated = $request->validate([
            'accion' => ['required', Rule::in(['aceptar', 'rechazar'])],
        ]);

        if ($validated['accion'] === 'aceptar') {
            $permuta->update([
                'estado' => 'pendiente_aprobacion',
                'aceptado_destinatario_at' => now(),
            ]);

            // F5-04: Notificar a Jefatura / Tutoría que la solicitud está lista para firma
            $revisoresQuery = User::where('especialidad_id', $especialidadId)
                ->whereHas('roles', function ($q) use ($permuta) {
                    $rolesRevisor = $permuta->colectivo === GuardiaGeneratorService::COLECTIVO_RESIDENTES
                        ? ['Jefe de Servicio', 'Tutor de Residentes']
                        : ['Jefe de Servicio'];
                    $q->whereIn('name', $rolesRevisor);
                });

            foreach ($revisoresQuery->get() as $revisor) {
                $revisor->notify(new PermutaPropuestaNotification($permuta, 'aprobacion'));
            }

            return back()->with('success', 'Has aceptado el intercambio. La solicitud ha pasado a firma de Jefatura/Tutoría.');
        }

        $permuta->update([
            'estado' => 'rechazada',
            'resuelto_at' => now(),
        ]);

        if ($permuta->solicitante) {
            $permuta->solicitante->notify(new PermutaResueltaNotification($permuta));
        }

        return back()->with('success', 'Has rechazado la propuesta de intercambio.');
    }

    /**
     * F5-01: Paso 2 de doble validación — Jefatura o Tutoría firma la solicitud ya aceptada por el compañero.
     */
    public function resolver(Request $request, SolicitudPermuta $permuta): RedirectResponse
    {
        $usuario = $request->user();
        $especialidadId = (int) $usuario->especialidad_id;

        if ((int) $permuta->especialidad_id !== $especialidadId) {
            abort(403);
        }

        $esJefe = $usuario->hasRole('Jefe de Servicio');
        $esTutor = $usuario->hasRole('Tutor de Residentes');

        if (!$esJefe && !$esTutor) {
            abort(403, 'Se requiere perfil de Jefatura o Tutoría para resolver permutas.');
        }

        if ($esTutor && !$esJefe && $permuta->colectivo !== GuardiaGeneratorService::COLECTIVO_RESIDENTES) {
            abort(403, 'El Tutor de Residentes solo puede autorizar permutas del colectivo de residentes.');
        }

        if (in_array($permuta->estado, ['pendiente_companero', 'abierta_bolsa'], true)) {
            return back()->withErrors([
                'conflicto' => 'El compañero destinatario o un voluntario debe aceptar primero el turno antes de la firma de Jefatura/Tutoría.',
            ]);
        }

        if (!in_array($permuta->estado, ['pendiente', 'pendiente_aprobacion'], true)) {
            return back()->withErrors(['conflicto' => 'Esta solicitud ya fue resuelta anteriormente.']);
        }

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['aprobada', 'rechazada'])],
        ]);

        if ($validated['estado'] === 'aprobada') {
            $esVacante = ($permuta->tipo_solicitud ?? 'permuta') === 'cobertura_vacante' || $permuta->guardia_destino_id === null;

            if ($esVacante) {
                if ($permuta->destinatario_id === null) {
                    return back()->withErrors(['conflicto' => 'No hay ningún voluntario asignado para cubrir esta vacante.']);
                }

                $error = $this->generatorService->ejecutarCoberturaVacante(
                    (int) $permuta->guardia_origen_id,
                    (int) $permuta->destinatario_id,
                    $especialidadId,
                    (string) $permuta->colectivo
                );
            } else {
                $error = $this->generatorService->ejecutarPermuta(
                    (int) $permuta->guardia_origen_id,
                    (int) $permuta->guardia_destino_id,
                    $especialidadId,
                    (string) $permuta->colectivo
                );
            }

            if ($error !== null) {
                return back()->withErrors(['conflicto' => $error]);
            }

            $permuta->update([
                'estado' => 'aprobada',
                'revisado_por' => $usuario->id,
                'resuelto_at' => now(),
            ]);

            if ($permuta->solicitante) {
                $permuta->solicitante->notify(new PermutaResueltaNotification($permuta));
            }
            if ($permuta->destinatario) {
                $permuta->destinatario->notify(new PermutaResueltaNotification($permuta));
            }

            $idsUsuariosSync = array_values(array_filter([
                (int) $permuta->solicitante_id,
                $permuta->destinatario_id ? (int) $permuta->destinatario_id : null,
            ]));

            $idsGuardiasAfectadas = array_values(array_filter([
                (int) $permuta->guardia_origen_id,
                $permuta->guardia_destino_id ? (int) $permuta->guardia_destino_id : null,
            ]));

            SyncGoogleCalendarJob::dispatch(
                $especialidadId,
                $idsUsuariosSync,
                null,
                null,
                $idsGuardiasAfectadas
            );

            return back()->with('success', $esVacante
                ? 'Cobertura de vacante aprobada y turno reasignado al voluntario.'
                : 'Solicitud aprobada y turnos intercambiados en el cuadrante.');
        }

        $permuta->update([
            'estado' => 'rechazada',
            'revisado_por' => $usuario->id,
            'resuelto_at' => now(),
        ]);

        if ($permuta->solicitante) {
            $permuta->solicitante->notify(new PermutaResueltaNotification($permuta));
        }
        if ($permuta->destinatario) {
            $permuta->destinatario->notify(new PermutaResueltaNotification($permuta));
        }

        return back()->with('success', 'Solicitud de intercambio denegada.');
    }

    public function destroy(Request $request, SolicitudPermuta $permuta): RedirectResponse
    {
        $usuario = $request->user();
        if ((int) $permuta->especialidad_id !== (int) $usuario->especialidad_id) {
            abort(403);
        }

        $esJefe = $usuario->hasRole('Jefe de Servicio');
        if (!$esJefe && (int) $permuta->solicitante_id !== (int) $usuario->id) {
            abort(403);
        }

        if (!in_array($permuta->estado, self::ESTADOS_ACTIVOS, true) && !$esJefe) {
            abort(403, 'No se puede cancelar una solicitud ya resuelta.');
        }

        $permuta->delete();

        return back()->with('success', 'Solicitud de permuta cancelada.');
    }
}
