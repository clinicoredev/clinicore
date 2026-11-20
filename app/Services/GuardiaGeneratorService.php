<?php

namespace App\Services;

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Ausencia;
use App\Models\Festivo;
use App\Models\Guardia;
use App\Models\LimitacionGuardia;
use App\Models\User;
use App\Notifications\CuadrantePublicadoNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuardiaGeneratorService
{
    public const COLECTIVO_ADJUNTOS = 'adjuntos';
    public const COLECTIVO_RESIDENTES = 'residentes';

    /**
     * Construye la consulta base de facultativos según especialidad y colectivo.
     *
     * @return Builder<User>
     */
    public function queryMedicosColectivo(int $especialidadId, string $colectivo): Builder
    {
        $query = User::where('especialidad_id', $especialidadId);

        if ($colectivo === self::COLECTIVO_RESIDENTES) {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Residente', 'Residente Mayor']));
        } else {
            $query->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['SuperAdmin', 'Residente', 'Residente Mayor']));
        }

        return $query;
    }

    /**
     * Obtiene la colección de facultativos del colectivo en la especialidad.
     *
     * @return Collection<int, User>
     */
    public function obtenerMedicosColectivo(int $especialidadId, string $colectivo): Collection
    {
        return $this->queryMedicosColectivo($especialidadId, $colectivo)->get();
    }

    /**
     * Valida que un usuario pertenezca al tenant (especialidad_id) y al colectivo indicado.
     */
    public function validarFacultativoEnColectivo(int $userId, int $especialidadId, string $colectivo): void
    {
        $esValido = $this->queryMedicosColectivo($especialidadId, $colectivo)
            ->where('id', $userId)
            ->exists();

        if (!$esValido) {
            $etiqueta = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';
            abort(403, "El {$etiqueta} no pertenece a este servicio o colectivo.");
        }
    }

    /**
     * Obtiene el mapa de festivos de una especialidad indexado por fecha YYYY-MM-DD.
     *
     * @return array<string, array{id: int, fecha: string, es_especial: bool, descripcion: string}>
     */
    public function obtenerMapaFestivos(int $especialidadId, ?int $anio = null, ?string $fechaDesde = null): array
    {
        $query = Festivo::where('especialidad_id', $especialidadId);

        if ($anio !== null) {
            $query->whereYear('fecha', $anio);
        }

        if ($fechaDesde !== null) {
            $query->where('fecha', '>=', $fechaDesde);
        }

        $festivos = $query->get();
        $mapa = [];

        foreach ($festivos as $f) {
            $fStr = Carbon::parse($f->fecha)->format('Y-m-d');
            $rawDesc = (string) ($f->descripcion ?: 'Festivo Oficial');
            $upper = strtoupper($rawDesc);
            $esEsp = str_starts_with($upper, '[ESPECIAL]')
                || str_contains($upper, '[ESPECIAL]')
                || str_contains($upper, '(ESPECIAL)');

            $descLimpia = trim((string) str_ireplace(['[ESPECIAL]', '(ESPECIAL)'], '', $rawDesc)) ?: 'Festivo';

            $mapa[$fStr] = [
                'id' => (int) $f->id,
                'fecha' => $fStr,
                'es_especial' => $esEsp,
                'descripcion' => $descLimpia,
            ];
        }

        return $mapa;
    }

    /**
     * Clasifica de forma unificada la tipología de horas de una guardia (17h, 24h no festivo, 24h festivo, 24h [ESPECIAL]) y su modalidad.
     *
     * @param array<string, array{id: int, fecha: string, es_especial: bool, descripcion: string}> $mapaFestivos
     * @return array{
     *   fecha_str: string,
     *   horas: int,
     *   horas_efectivas_computo: int,
     *   modalidad: string,
     *   etiqueta_modalidad: string,
     *   es_24h: bool,
     *   es_finde_no_festivo: bool,
     *   es_festivo_oficial: bool,
     *   es_festivo_especial: bool,
     *   categoria: string,
     *   etiqueta: string,
     *   festivo_descripcion: string|null
     * }
     */
    public function clasificarTipologiaGuardia(Guardia $guardia, array $mapaFestivos): array
    {
        $fechaObj = Carbon::parse($guardia->fecha);
        $fechaStr = $fechaObj->format('Y-m-d');
        $infoFestivo = $mapaFestivos[$fechaStr] ?? null;
        $esFestivoOficial = $infoFestivo !== null;
        $esFestivoEspecial = (bool) ($infoFestivo['es_especial'] ?? false);
        $es24h = $guardia->tipo === 'festivo_24h' || $esFestivoOficial || $fechaObj->isWeekend();
        $modalidad = (string) ($guardia->getAttribute('modalidad') ?: 'presencial');
        $esLocalizada = $modalidad === 'localizada';
        $horasNominales = $es24h ? 24 : 17;
        $horasEfectivas = $horasNominales;

        if ($es24h) {
            if ($esFestivoEspecial) {
                $categoria = 'festivo_especial_24h';
                $etiqueta = '24h (★ Especial)';
            } elseif ($esFestivoOficial) {
                $categoria = 'festivo_24h';
                $etiqueta = '24h (Festivo)';
            } else {
                $categoria = 'finde_24h';
                $etiqueta = '24h (Fin de Semana)';
            }
        } else {
            $categoria = 'diaria_17h';
            $etiqueta = '17h (Diaria)';
        }

        if ($esLocalizada) {
            $etiqueta .= ' [Localizada]';
        }

        return [
            'fecha_str' => $fechaStr,
            'horas' => $horasNominales,
            'horas_efectivas_computo' => $horasEfectivas,
            'modalidad' => $modalidad,
            'etiqueta_modalidad' => $esLocalizada ? 'Localizada' : 'Presencial',
            'es_24h' => $es24h,
            'es_finde_no_festivo' => $es24h && !$esFestivoOficial,
            'es_festivo_oficial' => $esFestivoOficial,
            'es_festivo_especial' => $esFestivoEspecial,
            'categoria' => $categoria,
            'etiqueta' => $etiqueta,
            'festivo_descripcion' => $infoFestivo['descripcion'] ?? null,
        ];
    }

    /**
     * Comprueba si un facultativo tiene conflicto con exenciones, ausencias aprobadas o limitaciones en una fecha.
     */
    public function checkConflict(
        int $userId,
        string|Carbon $fechaInput,
        bool $protegerVispera = true,
        string $colectivo = self::COLECTIVO_ADJUNTOS
    ): ?string {
        $fecha = ($fechaInput instanceof Carbon ? $fechaInput->copy() : Carbon::parse($fechaInput))->startOfDay();
        $sujeto = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';

        $usuario = User::find($userId);
        if ($usuario && (bool) ($usuario->exento_guardias ?? false)) {
            $motivoExencion = $usuario->motivo_exencion ? " ({$usuario->motivo_exencion})" : '';
            return "El {$sujeto} está exento de realizar guardias{$motivoExencion}.";
        }

        $ausencias = Ausencia::where('user_id', $userId)
            ->where('estado', 'aprobada')
            ->where('tipo', '!=', 'rotacion_con_guardias')
            ->get();

        foreach ($ausencias as $a) {
            $ini = Carbon::parse($a->fecha_inicio)->startOfDay();
            $fin = Carbon::parse($a->fecha_fin)->endOfDay();
            if ($fecha->between($ini, $fin)) {
                return "El {$sujeto} tiene una ausencia aprobada ({$a->tipo}) en esta fecha.";
            }
            if ($protegerVispera && $a->tipo === 'vacaciones' && $fecha->copy()->addDay()->isSameDay($ini)) {
                return "El {$sujeto} comienza sus vacaciones al día siguiente (víspera protegida).";
            }
        }

        $limites = LimitacionGuardia::where('user_id', $userId)->get();
        foreach ($limites as $l) {
            if ($l->tipo === 'dia_semana' && (int) $fecha->dayOfWeekIso === (int) $l->valor) {
                return 'Entra en conflicto con una regla recurrente de día de la semana.';
            }
            if ($l->tipo === 'fecha_concreta' && $fecha->format('Y-m-d') === $l->valor) {
                return "El {$sujeto} tiene un veto puntual exacto en esta fecha.";
            }
            if ($l->tipo === 'periodo') {
                $parts = explode(',', (string) $l->valor);
                if (
                    count($parts) === 2 &&
                    $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())
                ) {
                    return 'Entra en conflicto con un periodo bloqueado.';
                }
            }
        }

        return null;
    }

    /**
     * Comprueba si al mover o asignar una guardia en nuevaFecha se produce colisión el mismo día o violación de saliente/distancia mínima
     * (incluyendo el traslado de saliente de sábado en domingo al lunes).
     */
    public function checkPermutaColisionOSaliente(
        int $userId,
        string|Carbon $nuevaFechaInput,
        int $guardiaExcluidaId,
        int $distanciaMinimaDias = 1
    ): ?string {
        $fecha = ($nuevaFechaInput instanceof Carbon ? $nuevaFechaInput->copy() : Carbon::parse($nuevaFechaInput))->startOfDay();

        $mismoDiaQuery = Guardia::where('user_id', $userId)
            ->whereDate('fecha', $fecha->toDateString());
        if ($guardiaExcluidaId > 0) {
            $mismoDiaQuery->where('id', '!=', $guardiaExcluidaId);
        }

        if ($mismoDiaQuery->exists()) {
            return "Ya tiene una guardia asignada el {$fecha->format('d/m/Y')}.";
        }

        if ($distanciaMinimaDias > 0) {
            // Regla específica: Guardia en sábado traslada el saliente de domingo al lunes
            if ((int) $fecha->dayOfWeekIso === 1) {
                $sabadoPrevio = $fecha->copy()->subDays(2);
                $querySabado = Guardia::where('user_id', $userId)
                    ->whereDate('fecha', $sabadoPrevio->toDateString());
                if ($guardiaExcluidaId > 0) {
                    $querySabado->where('id', '!=', $guardiaExcluidaId);
                }
                if ($querySabado->exists()) {
                    return "Incumple el descanso obligatorio de saliente trasladado al lunes ({$fecha->format('d/m/Y')}) tras guardia en sábado ({$sabadoPrevio->format('d/m/Y')}).";
                }
            }

            if ((int) $fecha->dayOfWeekIso === 6) {
                $lunesSiguiente = $fecha->copy()->addDays(2);
                $queryLunes = Guardia::where('user_id', $userId)
                    ->whereDate('fecha', $lunesSiguiente->toDateString());
                if ($guardiaExcluidaId > 0) {
                    $queryLunes->where('id', '!=', $guardiaExcluidaId);
                }
                if ($queryLunes->exists()) {
                    return "Incumple el descanso obligatorio de saliente trasladado al lunes ({$lunesSiguiente->format('d/m/Y')}) por guardia en sábado ({$fecha->format('d/m/Y')}).";
                }
            }

            $salienteQuery = Guardia::where('user_id', $userId)
                ->whereDate('fecha', '>=', $fecha->copy()->subDays($distanciaMinimaDias)->toDateString())
                ->whereDate('fecha', '<=', $fecha->copy()->addDays($distanciaMinimaDias)->toDateString());
            if ($guardiaExcluidaId > 0) {
                $salienteQuery->where('id', '!=', $guardiaExcluidaId);
            }

            if ($salienteQuery->exists()) {
                return "Incumple el descanso obligatorio de salientes alrededor del {$fecha->format('d/m/Y')}.";
            }
        }

        return null;
    }

    /**
     * Ejecuta una permuta validando tenant, colectivo, ausencias, limitaciones, unicidad y salientes.
     * Devuelve null si tiene éxito o el mensaje de error si hay conflicto.
     */
    public function ejecutarPermuta(
        int $origenId,
        int $destinoId,
        int $especialidadId,
        string $colectivo
    ): ?string {
        $guardia1 = Guardia::with('facultativo')->findOrFail($origenId);
        $guardia2 = Guardia::with('facultativo')->findOrFail($destinoId);

        if ($guardia1->especialidad_id !== $especialidadId || $guardia2->especialidad_id !== $especialidadId) {
            abort(403);
        }

        $sujeto = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';

        if ($guardia1->id === $guardia2->id || $guardia1->user_id === $guardia2->user_id) {
            return "No se puede permutar una guardia consigo misma o entre el mismo {$sujeto}.";
        }

        $this->validarFacultativoEnColectivo((int) $guardia1->user_id, $especialidadId, $colectivo);
        $this->validarFacultativoEnColectivo((int) $guardia2->user_id, $especialidadId, $colectivo);

        $etiquetaDefecto = $colectivo === self::COLECTIVO_RESIDENTES ? 'Residente' : 'Facultativo';
        $nombre1 = $guardia1->facultativo->name ?? $etiquetaDefecto;
        $nombre2 = $guardia2->facultativo->name ?? $etiquetaDefecto;

        if ($conflicto1 = $this->checkConflict((int) $guardia1->user_id, $guardia2->fecha, false, $colectivo)) {
            return "Permuta denegada para {$nombre1}: " . $conflicto1;
        }
        if ($conflicto2 = $this->checkConflict((int) $guardia2->user_id, $guardia1->fecha, false, $colectivo)) {
            return "Permuta denegada para {$nombre2}: " . $conflicto2;
        }
        if ($colision1 = $this->checkPermutaColisionOSaliente((int) $guardia1->user_id, $guardia2->fecha, (int) $guardia1->id)) {
            return "Permuta denegada para {$nombre1}: " . $colision1;
        }
        if ($colision2 = $this->checkPermutaColisionOSaliente((int) $guardia2->user_id, $guardia1->fecha, (int) $guardia2->id)) {
            return "Permuta denegada para {$nombre2}: " . $colision2;
        }

        $tempUserId = $guardia1->user_id;
        $guardia1->user_id = $guardia2->user_id;
        $guardia2->user_id = $tempUserId;
        $guardia1->is_manual = true;
        $guardia2->is_manual = true;
        $guardia1->observaciones = 'Permuta manual';
        $guardia2->observaciones = 'Permuta manual';
        $guardia1->save();
        $guardia2->save();

        return null;
    }

    /**
     * F6-05: Ejecuta la cobertura de un turno vacante por parte de un voluntario del mismo colectivo,
     * validando aislamiento multi-tenant, ausencias, limitaciones, exenciones y descansos de saliente (incluyendo sábado -> lunes).
     */
    public function ejecutarCoberturaVacante(
        int $guardiaOrigenId,
        int $voluntarioId,
        int $especialidadId,
        string $colectivo
    ): ?string {
        $guardiaOrigen = Guardia::with('facultativo')->findOrFail($guardiaOrigenId);

        if ((int) $guardiaOrigen->especialidad_id !== $especialidadId) {
            abort(403);
        }

        $sujeto = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';

        if ((int) $guardiaOrigen->user_id === $voluntarioId) {
            return "El {$sujeto} voluntario ya es el titular actual de este turno.";
        }

        $this->validarFacultativoEnColectivo((int) $guardiaOrigen->user_id, $especialidadId, $colectivo);
        $this->validarFacultativoEnColectivo($voluntarioId, $especialidadId, $colectivo);

        $voluntario = User::findOrFail($voluntarioId);
        $nombreVoluntario = $voluntario->name;

        if ($conflicto = $this->checkConflict($voluntarioId, $guardiaOrigen->fecha, false, $colectivo)) {
            return "Cobertura denegada para {$nombreVoluntario}: " . $conflicto;
        }

        if ($colision = $this->checkPermutaColisionOSaliente($voluntarioId, $guardiaOrigen->fecha, (int) $guardiaOrigen->id, 1)) {
            return "Cobertura denegada para {$nombreVoluntario}: " . $colision;
        }

        $guardiaOrigen->user_id = $voluntarioId;
        $guardiaOrigen->is_manual = true;
        $guardiaOrigen->observaciones = 'Cobertura de vacante / baja sobrevenida';
        $guardiaOrigen->save();

        return null;
    }

    /**
     * Evalúa en tiempo real (F3-03) todos los posibles conflictos de una asignación manual o de una permuta.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *   valido: bool,
     *   bloqueante: bool,
     *   forzable: bool,
     *   conflictos: array<int, array{user_id: int, facultativo: string, fecha: string, fecha_formateada: string, tipo: string, severidad: string, mensaje: string}>,
     *   mensaje_resumen: string|null
     * }
     */
    public function evaluarConflictosEnTiempoReal(int $especialidadId, string $colectivo, array $payload): array
    {
        $modo = (string) ($payload['modo'] ?? (isset($payload['origen_id'], $payload['destino_id']) ? 'permuta' : 'asignacion'));
        $distanciaMinimaDias = isset($payload['distancia_minima_dias']) ? (int) $payload['distancia_minima_dias'] : 1;

        if ($modo === 'permuta') {
            $origenId = (int) ($payload['origen_id'] ?? 0);
            $destinoId = (int) ($payload['destino_id'] ?? 0);

            $guardia1 = Guardia::with('facultativo')->findOrFail($origenId);
            $guardia2 = Guardia::with('facultativo')->findOrFail($destinoId);

            if ((int) $guardia1->especialidad_id !== $especialidadId || (int) $guardia2->especialidad_id !== $especialidadId) {
                abort(403, 'Acceso cross-tenant denegado.');
            }

            $this->validarFacultativoEnColectivo((int) $guardia1->user_id, $especialidadId, $colectivo);
            $this->validarFacultativoEnColectivo((int) $guardia2->user_id, $especialidadId, $colectivo);

            $sujeto = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';
            $etiquetaDefecto = $colectivo === self::COLECTIVO_RESIDENTES ? 'Residente' : 'Facultativo';
            $nombre1 = $guardia1->facultativo->name ?? $etiquetaDefecto;
            $nombre2 = $guardia2->facultativo->name ?? $etiquetaDefecto;

            if ($guardia1->id === $guardia2->id || $guardia1->user_id === $guardia2->user_id) {
                $msg = "No se puede permutar una guardia consigo misma o entre el mismo {$sujeto}.";
                return [
                    'valido' => false,
                    'bloqueante' => true,
                    'forzable' => false,
                    'conflictos' => [[
                        'user_id' => (int) $guardia1->user_id,
                        'facultativo' => $nombre1,
                        'fecha' => Carbon::parse($guardia1->fecha)->format('Y-m-d'),
                        'fecha_formateada' => Carbon::parse($guardia1->fecha)->format('d/m/Y'),
                        'tipo' => 'mismo_facultativo',
                        'severidad' => 'bloqueante',
                        'mensaje' => $msg,
                    ]],
                    'mensaje_resumen' => $msg,
                ];
            }

            $conflictos1 = $this->diagnosticarConflictosFacultativoEnFecha(
                (int) $guardia1->user_id,
                $nombre1,
                Carbon::parse($guardia2->fecha),
                (int) $guardia1->id,
                $distanciaMinimaDias,
                $colectivo,
                true
            );

            $conflictos2 = $this->diagnosticarConflictosFacultativoEnFecha(
                (int) $guardia2->user_id,
                $nombre2,
                Carbon::parse($guardia1->fecha),
                (int) $guardia2->id,
                $distanciaMinimaDias,
                $colectivo,
                true
            );

            $conflictos = array_merge($conflictos1, $conflictos2);
            $tieneBloqueantes = collect($conflictos)->contains(fn(array $c): bool => $c['severidad'] === 'bloqueante');

            return [
                'valido' => count($conflictos) === 0,
                'bloqueante' => $tieneBloqueantes,
                'forzable' => false,
                'conflictos' => $conflictos,
                'mensaje_resumen' => count($conflictos) > 0
                    ? implode(' ', array_map(fn(array $c): string => "{$c['facultativo']}: {$c['mensaje']}", $conflictos))
                    : null,
            ];
        }

        // Modo asignación manual / arrastrar a día
        $userId = (int) ($payload['user_id'] ?? 0);
        $fechaStr = (string) ($payload['fecha'] ?? '');
        $guardiaExcluidaId = (int) ($payload['guardia_excluida_id'] ?? 0);

        $this->validarFacultativoEnColectivo($userId, $especialidadId, $colectivo);
        $medico = User::where('especialidad_id', $especialidadId)->findOrFail($userId);
        $fecha = Carbon::parse($fechaStr)->startOfDay();

        $conflictos = $this->diagnosticarConflictosFacultativoEnFecha(
            $userId,
            $medico->name,
            $fecha,
            $guardiaExcluidaId,
            $distanciaMinimaDias,
            $colectivo,
            false
        );

        $tieneBloqueantes = collect($conflictos)->contains(fn(array $c): bool => $c['severidad'] === 'bloqueante');

        return [
            'valido' => count($conflictos) === 0,
            'bloqueante' => $tieneBloqueantes,
            'forzable' => count($conflictos) > 0 && !$tieneBloqueantes,
            'conflictos' => $conflictos,
            'mensaje_resumen' => count($conflictos) > 0
                ? implode(' ', array_column($conflictos, 'mensaje'))
                : null,
        ];
    }

    /**
     * Diagnostica de forma granular todos los conflictos de un facultativo en una fecha dada.
     *
     * @return array<int, array{user_id: int, facultativo: string, fecha: string, fecha_formateada: string, tipo: string, severidad: string, mensaje: string}>
     */
    private function diagnosticarConflictosFacultativoEnFecha(
        int $userId,
        string $nombreFacultativo,
        Carbon $fechaInput,
        int $guardiaExcluidaId,
        int $distanciaMinimaDias,
        string $colectivo,
        bool $esPermutaEstricto
    ): array {
        $fecha = $fechaInput->copy()->startOfDay();
        $fechaStr = $fecha->format('Y-m-d');
        $fechaFmt = $fecha->format('d/m/Y');
        $sujeto = $colectivo === self::COLECTIVO_RESIDENTES ? 'residente' : 'facultativo';
        $severidadVeto = $esPermutaEstricto ? 'bloqueante' : 'advertencia';
        $conflictos = [];

        // 0. Exención de guardias (F5-02)
        $usuarioRef = User::find($userId);
        if ($usuarioRef && (bool) ($usuarioRef->exento_guardias ?? false)) {
            $motivoEx = $usuarioRef->motivo_exencion ? " ({$usuarioRef->motivo_exencion})" : '';
            $conflictos[] = [
                'user_id' => $userId,
                'facultativo' => $nombreFacultativo,
                'fecha' => $fechaStr,
                'fecha_formateada' => $fechaFmt,
                'tipo' => 'exencion_guardias',
                'severidad' => $severidadVeto,
                'mensaje' => "El {$sujeto} está exento de realizar guardias{$motivoEx}.",
            ];
        }

        // 1. Ausencias aprobadas y víspera de vacaciones
        $ausencias = Ausencia::where('user_id', $userId)
            ->where('estado', 'aprobada')
            ->where('tipo', '!=', 'rotacion_con_guardias')
            ->get();

        foreach ($ausencias as $a) {
            $ini = Carbon::parse($a->fecha_inicio)->startOfDay();
            $fin = Carbon::parse($a->fecha_fin)->endOfDay();
            if ($fecha->between($ini, $fin)) {
                $conflictos[] = [
                    'user_id' => $userId,
                    'facultativo' => $nombreFacultativo,
                    'fecha' => $fechaStr,
                    'fecha_formateada' => $fechaFmt,
                    'tipo' => 'ausencia',
                    'severidad' => $severidadVeto,
                    'mensaje' => "El {$sujeto} tiene una ausencia aprobada ({$a->tipo}) el {$fechaFmt}.",
                ];
            } elseif ($a->tipo === 'vacaciones' && $fecha->copy()->addDay()->isSameDay($ini)) {
                $conflictos[] = [
                    'user_id' => $userId,
                    'facultativo' => $nombreFacultativo,
                    'fecha' => $fechaStr,
                    'fecha_formateada' => $fechaFmt,
                    'tipo' => 'vispera_vacaciones',
                    'severidad' => 'advertencia',
                    'mensaje' => "El {$sujeto} comienza sus vacaciones al día siguiente ({$ini->format('d/m/Y')}).",
                ];
            }
        }

        // 2. Limitaciones de guardia
        $limites = LimitacionGuardia::where('user_id', $userId)->get();
        foreach ($limites as $l) {
            if ($l->tipo === 'dia_semana' && (int) $fecha->dayOfWeekIso === (int) $l->valor) {
                $conflictos[] = [
                    'user_id' => $userId,
                    'facultativo' => $nombreFacultativo,
                    'fecha' => $fechaStr,
                    'fecha_formateada' => $fechaFmt,
                    'tipo' => 'limitacion_dia_semana',
                    'severidad' => $severidadVeto,
                    'mensaje' => 'Entra en conflicto con una regla recurrente de día de la semana.',
                ];
            } elseif ($l->tipo === 'fecha_concreta' && $fechaStr === $l->valor) {
                $conflictos[] = [
                    'user_id' => $userId,
                    'facultativo' => $nombreFacultativo,
                    'fecha' => $fechaStr,
                    'fecha_formateada' => $fechaFmt,
                    'tipo' => 'limitacion_fecha',
                    'severidad' => $severidadVeto,
                    'mensaje' => "El {$sujeto} tiene un veto puntual exacto en esta fecha ({$fechaFmt}).",
                ];
            } elseif ($l->tipo === 'periodo') {
                $parts = explode(',', (string) $l->valor);
                if (
                    count($parts) === 2 &&
                    $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())
                ) {
                    $conflictos[] = [
                        'user_id' => $userId,
                        'facultativo' => $nombreFacultativo,
                        'fecha' => $fechaStr,
                        'fecha_formateada' => $fechaFmt,
                        'tipo' => 'limitacion_periodo',
                        'severidad' => $severidadVeto,
                        'mensaje' => 'Entra en conflicto con un periodo bloqueado.',
                    ];
                }
            }
        }

        // 3. Colisión en el mismo día
        $queryMismoDia = Guardia::where('user_id', $userId)
            ->whereDate('fecha', $fechaStr);
        if ($guardiaExcluidaId > 0) {
            $queryMismoDia->where('id', '!=', $guardiaExcluidaId);
        }
        if ($queryMismoDia->exists()) {
            $conflictos[] = [
                'user_id' => $userId,
                'facultativo' => $nombreFacultativo,
                'fecha' => $fechaStr,
                'fecha_formateada' => $fechaFmt,
                'tipo' => 'colision_mismo_dia',
                'severidad' => $esPermutaEstricto ? 'bloqueante' : 'advertencia',
                'mensaje' => "Ya tiene una guardia asignada el {$fechaFmt}.",
            ];
        }

        // 4. Salientes / Distancia mínima y Regla Sábado -> Lunes (saliente trasladado de domingo)
        if ($distanciaMinimaDias > 0) {
            if ((int) $fecha->dayOfWeekIso === 1) {
                $sabadoPrevio = $fecha->copy()->subDays(2);
                $querySabado = Guardia::where('user_id', $userId)
                    ->whereDate('fecha', $sabadoPrevio->toDateString());
                if ($guardiaExcluidaId > 0) {
                    $querySabado->where('id', '!=', $guardiaExcluidaId);
                }
                if ($querySabado->exists()) {
                    $conflictos[] = [
                        'user_id' => $userId,
                        'facultativo' => $nombreFacultativo,
                        'fecha' => $fechaStr,
                        'fecha_formateada' => $fechaFmt,
                        'tipo' => 'saliente_lunes_por_sabado',
                        'severidad' => $severidadVeto,
                        'mensaje' => "Incumple el descanso obligatorio de saliente trasladado al lunes tras guardia en sábado ({$sabadoPrevio->format('d/m/Y')}).",
                    ];
                }
            }

            if ((int) $fecha->dayOfWeekIso === 6) {
                $lunesSiguiente = $fecha->copy()->addDays(2);
                $queryLunes = Guardia::where('user_id', $userId)
                    ->whereDate('fecha', $lunesSiguiente->toDateString());
                if ($guardiaExcluidaId > 0) {
                    $queryLunes->where('id', '!=', $guardiaExcluidaId);
                }
                if ($queryLunes->exists()) {
                    $conflictos[] = [
                        'user_id' => $userId,
                        'facultativo' => $nombreFacultativo,
                        'fecha' => $fechaStr,
                        'fecha_formateada' => $fechaFmt,
                        'tipo' => 'saliente_lunes_por_sabado',
                        'severidad' => $severidadVeto,
                        'mensaje' => "Incumple el descanso obligatorio de saliente trasladado al lunes (tiene turno el lunes {$lunesSiguiente->format('d/m/Y')}).",
                    ];
                }
            }

            $querySaliente = Guardia::where('user_id', $userId)
                ->whereDate('fecha', '!=', $fechaStr)
                ->whereDate('fecha', '>=', $fecha->copy()->subDays($distanciaMinimaDias)->toDateString())
                ->whereDate('fecha', '<=', $fecha->copy()->addDays($distanciaMinimaDias)->toDateString());

            if ($guardiaExcluidaId > 0) {
                $querySaliente->where('id', '!=', $guardiaExcluidaId);
            }

            $guardiaCercana = $querySaliente->first();
            if ($guardiaCercana !== null) {
                $fechaCercanaFmt = Carbon::parse($guardiaCercana->fecha)->format('d/m/Y');
                $conflictos[] = [
                    'user_id' => $userId,
                    'facultativo' => $nombreFacultativo,
                    'fecha' => $fechaStr,
                    'fecha_formateada' => $fechaFmt,
                    'tipo' => 'saliente_distancia_minima',
                    'severidad' => $severidadVeto,
                    'mensaje' => "Incumple el descanso obligatorio de salientes (tiene turno el {$fechaCercanaFmt}).",
                ];
            }
        }

        return $conflictos;
    }

    /**
     * Vacía todos los turnos de un colectivo en un mes y registra un evento de auditoría de lote (F3-02).
     */
    public function vaciarMesColectivo(
        int $especialidadId,
        string $colectivo,
        int $mes,
        int $anio,
        ?User $actor = null
    ): int {
        $idsColectivo = $this->queryMedicosColectivo($especialidadId, $colectivo)
            ->pluck('id')
            ->toArray();

        $eliminados = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->delete();

        $etiquetaColectivo = $colectivo === self::COLECTIVO_RESIDENTES ? 'Residentes' : 'Adjuntos';
        $actorUser = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);

        $logger = activity('cuadrante')
            ->event('batch_cleared')
            ->withProperties([
                'especialidad_id' => $especialidadId,
                'colectivo' => $colectivo,
                'mes' => $mes,
                'anio' => $anio,
                'old' => [
                    'colectivo' => $etiquetaColectivo,
                    'periodo' => sprintf('%02d/%d', $mes, $anio),
                    'turnos_eliminados' => $eliminados,
                ],
            ]);

        if ($actorUser !== null) {
            $logger->causedBy($actorUser);
        }

        $logger->log("Vaciado masivo de cuadrante ({$etiquetaColectivo}) del periodo {$mes}/{$anio}: {$eliminados} turnos eliminados.");

        return $eliminados;
    }

    /**
     * Calcula los días realmente disponibles de un médico en el mes (sin duplicar solapamientos).
     *
     * @param Collection<int, Ausencia> $ausencias
     * @param Collection<int, LimitacionGuardia> $limitaciones
     */
    public function calcularDiasDisponiblesMes(
        int $medicoId,
        int $anio,
        int $mes,
        Collection $ausencias,
        Collection $limitaciones
    ): int {
        $diasEnMes = Carbon::createFromDate($anio, $mes, 1)->daysInMonth;
        $disponibles = 0;
        $misAusencias = $ausencias->where('user_id', $medicoId);
        $misLimitaciones = $limitaciones->where('user_id', $medicoId);

        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha = Carbon::createFromDate($anio, $mes, $d)->startOfDay();
            if (!$this->estaBloqueadoPorAusenciaOLimitacion($fecha, $misAusencias, $misLimitaciones)) {
                $disponibles++;
            }
        }

        return $disponibles;
    }

    /**
     * Obtiene todos los datos estructurados para la vista Index de Guardias (Adjuntos o Residentes).
     *
     * @return array<string, mixed>
     */
    public function obtenerDatosIndex(int $especialidadId, int $mes, int $anio, string $colectivo): array
    {
        $medicos = $this->obtenerMedicosColectivo($especialidadId, $colectivo);
        $idsColectivo = $medicos->pluck('id')->toArray();

        $mapaFestivosAnio = $this->obtenerMapaFestivos($especialidadId, $anio);

        $festivosDelMes = collect($mapaFestivosAnio)
            ->filter(fn(array $f) => Carbon::parse($f['fecha'])->month === $mes)
            ->values();

        $guardias = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->with('facultativo')
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get()
            ->map(function (Guardia $g) use ($mapaFestivosAnio): array {
                $info = $this->clasificarTipologiaGuardia($g, $mapaFestivosAnio);
                $fechaCarbon = Carbon::parse($g->fecha);
                $fechaCarbon->locale('es');

                return [
                    'id' => $g->id,
                    'user_id' => $g->user_id,
                    'facultativo' => $g->facultativo ? $g->facultativo->name : 'Desconocido',
                    'fecha' => $info['fecha_str'],
                    'fecha_formateada' => $fechaCarbon->format('d/m/Y'),
                    'dia_semana' => ucfirst($fechaCarbon->dayName),
                    'tipo' => $g->tipo,
                    'modalidad' => $info['modalidad'],
                    'horas' => $info['horas'],
                    'horas_efectivas' => $info['horas_efectivas_computo'],
                    'es_festivo_oficial' => $info['es_festivo_oficial'],
                    'es_festivo_especial' => $info['es_festivo_especial'],
                    'tipo_badge' => $info['etiqueta'],
                    'es_finde' => $info['es_24h'],
                    'is_manual' => (bool) $g->is_manual,
                    'observaciones' => $g->observaciones,
                ];
            });

        $guardiasAnio = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->whereYear('fecha', $anio)
            ->get();

        $limitacionesRaw = LimitacionGuardia::with('facultativo')
            ->where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->get();

        $limitaciones = $limitacionesRaw->map(function (LimitacionGuardia $l): array {
            $mapaDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            $reglaLegible = '';
            if ($l->tipo === 'dia_semana') {
                $reglaLegible = 'Los ' . ($mapaDias[(int) $l->valor] ?? $l->valor);
            } elseif ($l->tipo === 'fecha_concreta') {
                $reglaLegible = Carbon::parse($l->valor)->format('d/m/Y');
            } elseif ($l->tipo === 'periodo') {
                $parts = explode(',', (string) $l->valor);
                $reglaLegible = count($parts) === 2
                    ? 'Del ' . Carbon::parse($parts[0])->format('d/m/Y') . ' al ' . Carbon::parse($parts[1])->format('d/m/Y')
                    : 'Rango inválido';
            }

            return [
                'id' => $l->id,
                'user_id' => $l->user_id,
                'medico' => $l->facultativo ? $l->facultativo->name : 'Borrado',
                'regla' => $reglaLegible,
                'tipo_raw' => $l->tipo,
                'valor' => $l->valor,
                'motivo' => $l->motivo ?: 'Sin motivo',
            ];
        });

        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

        // F6-03: Guardias de la misma semana ISO en el mes anterior para el aviso informativo de 48h/semana
        $inicioSemanaPrimerDia = $inicioMes->copy()->startOfWeek(Carbon::MONDAY);
        $horasPreviasSemanaIso = [];
        if ($inicioSemanaPrimerDia->lt($inicioMes)) {
            $mapaFestivosPrev = $this->obtenerMapaFestivos($especialidadId, (int) $inicioSemanaPrimerDia->year);
            $guardiasPrevSemana = Guardia::where('especialidad_id', $especialidadId)
                ->whereIn('user_id', $idsColectivo)
                ->whereDate('fecha', '>=', $inicioSemanaPrimerDia->toDateString())
                ->whereDate('fecha', '<', $inicioMes->toDateString())
                ->get();

            foreach ($guardiasPrevSemana as $gp) {
                $infoPrev = $this->clasificarTipologiaGuardia($gp, $mapaFestivosPrev);
                $semKeyPrev = Carbon::parse($gp->fecha)->format('o-\WW');
                $uIdPrev = (int) $gp->user_id;
                $horasPreviasSemanaIso[$uIdPrev][$semKeyPrev] = ($horasPreviasSemanaIso[$uIdPrev][$semKeyPrev] ?? 0) + $infoPrev['horas_efectivas_computo'];
            }
        }

        $ausenciasRaw = Ausencia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->where('estado', 'aprobada')
            ->where(function ($q) use ($inicioMes, $finMes) {
                $q->whereBetween('fecha_inicio', [$inicioMes, $finMes])
                    ->orWhereBetween('fecha_fin', [$inicioMes, $finMes])
                    ->orWhere(fn($sub) => $sub->where('fecha_inicio', '<', $inicioMes)->where('fecha_fin', '>', $finMes));
            })
            ->with('solicitante')
            ->get();

        $ausenciasMes = $ausenciasRaw->map(fn(Ausencia $a): array => [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'medico' => $a->solicitante ? $a->solicitante->name : 'Desconocido',
            'inicio' => Carbon::parse($a->fecha_inicio)->format('Y-m-d'),
            'fin' => Carbon::parse($a->fecha_fin)->format('Y-m-d'),
            'tipo_raw' => $a->tipo,
            'bloquea_guardias' => $a->tipo !== 'rotacion_con_guardias',
            'motivo' => ($a->tipo === 'rotacion_con_guardias' ? 'Rotación (Hace guardias): ' : '') . ($a->motivo ?: ucfirst(str_replace('_', ' ', (string) $a->tipo))),
        ]);

        $equidad = $medicos->map(function (User $medico) use ($guardias, $guardiasAnio, $mapaFestivosAnio, $anio, $mes, $ausenciasRaw, $limitacionesRaw, $horasPreviasSemanaIso): array {
            $misGuardias = $guardias->where('user_id', $medico->id);
            $diarias = $misGuardias->where('es_finde', false)->count();
            $festivosMes = $misGuardias->where('es_finde', true)->where('es_festivo_oficial', true)->count();
            $findes = $misGuardias->where('es_finde', true)->where('es_festivo_oficial', false)->count();
            $puntos = ($diarias * 1) + (($findes + $festivosMes) * 2);
            $diasDisp = $this->calcularDiasDisponiblesMes($medico->id, $anio, $mes, $ausenciasRaw, $limitacionesRaw);
            $horasEfectivasMes = (int) $misGuardias->sum('horas_efectivas');

            $semanasMedico = [];
            foreach ($misGuardias as $mg) {
                $semKey = Carbon::parse($mg['fecha'])->format('o-\WW');
                if (!isset($semanasMedico[$semKey])) {
                    $semanasMedico[$semKey] = (int) ($horasPreviasSemanaIso[(int) $medico->id][$semKey] ?? 0);
                }
                $semanasMedico[$semKey] += (int) ($mg['horas_efectivas'] ?? $mg['horas']);
            }
            $excedeTopeLegal = collect($semanasMedico)->contains(fn(int $h): bool => $h > 48);

            $festivosAnual = 0;
            $especialesAnual = 0;
            foreach ($guardiasAnio->where('user_id', $medico->id) as $ga) {
                $fStr = Carbon::parse($ga->fecha)->format('Y-m-d');
                if (isset($mapaFestivosAnio[$fStr])) {
                    $festivosAnual++;
                    if ($mapaFestivosAnio[$fStr]['es_especial']) {
                        $especialesAnual++;
                    }
                }
            }

            return [
                'id' => $medico->id,
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $medico->name),
                'porcentaje_jornada' => (int) ($medico->porcentaje_jornada ?? 100),
                'exento_guardias' => (bool) ($medico->exento_guardias ?? false),
                'dias_disponibles' => $diasDisp,
                'diarias' => $diarias,
                'findes' => $findes,
                'festivos' => $festivosMes,
                'festivos_anual' => $festivosAnual,
                'especiales_anual' => $especialesAnual,
                'totales' => $misGuardias->count(),
                'horas_totales' => $horasEfectivasMes,
                'excede_tope_legal' => $excedeTopeLegal,
                'puntos' => $puntos,
            ];
        })->sortByDesc('puntos')->values();

        // Bloqueos de saliente trasladado al lunes (incluyendo frontera si día 1 o 2 es lunes)
        $guardiasSabadoRango = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->with('facultativo')
            ->whereDate('fecha', '>=', $inicioMes->copy()->subDays(2)->toDateString())
            ->whereDate('fecha', '<=', $finMes->toDateString())
            ->get();

        $salientesLunes = $guardiasSabadoRango
            ->filter(function (Guardia $g) use ($mes, $anio): bool {
                $fSab = Carbon::parse($g->fecha);
                if ((int) $fSab->dayOfWeekIso !== 6) {
                    return false;
                }
                $fLun = $fSab->copy()->addDays(2);
                return (int) $fLun->month === $mes && (int) $fLun->year === $anio;
            })
            ->map(function (Guardia $g): array {
                $fSab = Carbon::parse($g->fecha);
                $fLun = $fSab->copy()->addDays(2);

                return [
                    'id' => 'sal_lun_' . $g->id,
                    'user_id' => (int) $g->user_id,
                    'medico' => $g->facultativo ? $g->facultativo->name : 'Desconocido',
                    'fecha' => $fLun->format('Y-m-d'),
                    'fecha_sabado' => $fSab->format('Y-m-d'),
                    'motivo' => 'Saliente trasladado de domingo (Guardia sáb. ' . $fSab->format('d/m') . ')',
                ];
            })
            ->values();

        return [
            'guardias' => $guardias,
            'limitaciones' => $limitaciones,
            'ausencias_mes' => $ausenciasMes,
            'salientes_lunes' => $salientesLunes,
            'festivos' => $festivosDelMes,
            'medicos' => $medicos,
            'equidad' => $equidad,
            'mes_actual' => $mes,
            'anio_actual' => $anio,
        ];
    }

    /**
     * Construye los datos unificados para exportar el cuadrante en PDF (diferenciando 17h, 24h no festivo, 24h festivo y [ESPECIAL]).
     *
     * @return array<string, mixed>
     */
    public function construirDatosReportePdf(int $especialidadId, int $mes, int $anio, string $colectivo): array
    {
        $medicos = $this->obtenerMedicosColectivo($especialidadId, $colectivo);
        $idsColectivo = $medicos->pluck('id')->toArray();
        $mapaFestivos = $this->obtenerMapaFestivos($especialidadId, $anio);

        $guardias = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->with('facultativo')
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha')
            ->get();

        foreach ($guardias as $g) {
            $info = $this->clasificarTipologiaGuardia($g, $mapaFestivos);
            $g->setAttribute('horas_computadas', $info['horas']);
            $g->setAttribute('horas_efectivas_computo', $info['horas_efectivas_computo']);
            $g->setAttribute('modalidad_turno', $info['modalidad']);
            $g->setAttribute('modalidad_etiqueta', $info['etiqueta_modalidad']);
            $g->setAttribute('es_24h', $info['es_24h']);
            $g->setAttribute('es_finde_no_festivo', $info['es_finde_no_festivo']);
            $g->setAttribute('es_festivo_oficial', $info['es_festivo_oficial']);
            $g->setAttribute('es_festivo_especial', $info['es_festivo_especial']);
            $g->setAttribute('tipo_etiqueta', $info['etiqueta']);
            $g->setAttribute('festivo_descripcion', $info['festivo_descripcion']);
        }

        $equidad = $medicos->map(function (User $medico) use ($guardias): object {
            $misGuardias = $guardias->where('user_id', $medico->id);
            $diarias17h = $misGuardias->filter(fn(Guardia $g) => !$g->getAttribute('es_24h'))->count();
            $findes24h = $misGuardias->filter(fn(Guardia $g) => (bool) $g->getAttribute('es_finde_no_festivo'))->count();
            $festivos24h = $misGuardias->filter(fn(Guardia $g) => (bool) $g->getAttribute('es_festivo_oficial'))->count();
            $especiales24h = $misGuardias->filter(fn(Guardia $g) => (bool) $g->getAttribute('es_festivo_especial'))->count();
            $presenciales = $misGuardias->filter(fn(Guardia $g) => $g->getAttribute('modalidad_turno') !== 'localizada')->count();
            $localizadas = $misGuardias->filter(fn(Guardia $g) => $g->getAttribute('modalidad_turno') === 'localizada')->count();
            $horasTotales = (int) $misGuardias->sum(fn(Guardia $g): int => (int) ($g->getAttribute('horas_efectivas_computo') ?? $g->getAttribute('horas_computadas')));

            return (object) [
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $medico->name),
                'diarias_17h' => $diarias17h,
                'findes_24h' => $findes24h,
                'festivos_24h' => $festivos24h,
                'especiales_24h' => $especiales24h,
                'presenciales' => $presenciales,
                'localizadas' => $localizadas,
                'findes' => $findes24h + $festivos24h,
                'totales' => $misGuardias->count(),
                'horas_totales' => $horasTotales,
            ];
        })->sortByDesc('horas_totales')->values();

        $fechaInicio = Carbon::createFromDate($anio, $mes, 1);
        $diasDelMes = $fechaInicio->daysInMonth;
        $primerDiaSemana = $fechaInicio->dayOfWeekIso;

        $calendario = [];
        $semana = [];
        for ($i = 1; $i < $primerDiaSemana; $i++) {
            $semana[] = null;
        }

        $guardiasPorFecha = $guardias->groupBy(fn(Guardia $g) => Carbon::parse($g->fecha)->format('Y-m-d'));

        for ($dia = 1; $dia <= $diasDelMes; $dia++) {
            $fechaDia = Carbon::createFromDate($anio, $mes, $dia);
            $fStr = $fechaDia->format('Y-m-d');
            $infoFestivo = $mapaFestivos[$fStr] ?? null;

            $semana[] = [
                'numero' => $dia,
                'es_finde' => $fechaDia->isWeekend() || $infoFestivo !== null,
                'es_festivo' => $infoFestivo !== null,
                'es_especial' => (bool) ($infoFestivo['es_especial'] ?? false),
                'festivo_nombre' => $infoFestivo['descripcion'] ?? null,
                'guardias' => $guardiasPorFecha->get($fStr, collect()),
            ];

            if (count($semana) === 7) {
                $calendario[] = $semana;
                $semana = [];
            }
        }

        if (count($semana) > 0) {
            while (count($semana) < 7) {
                $semana[] = null;
            }
            $calendario[] = $semana;
        }

        return [
            'guardias' => $guardias,
            'equidad' => $equidad,
            'calendario' => $calendario,
            'mes' => $mes,
            'anio' => $anio,
            'diasDelMes' => $diasDelMes,
            'primerDiaSemana' => $primerDiaSemana,
        ];
    }

    /**
     * Simula en modo Dry-Run (F3-01) el cuadrante automático sin persistir en base de datos.
     *
     * @param array<string, mixed> $params
     * @return array{
     *   ok: bool,
     *   error: string|null,
     *   resumen: array<string, int|string>,
     *   alertas_relajacion: array<int, array{fecha: string, fecha_formateada: string, user_id: int, facultativo: string, motivo: string}>,
     *   guardias_simuladas: array<int, array<string, mixed>>,
     *   equidad_simulada: array<int, array<string, mixed>>
     * }
     */
    public function simularCuadrante(int $especialidadId, string $colectivo, array $params): array
    {
        return $this->calcularCuadranteInterno($especialidadId, $colectivo, $params, false);
    }

    /**
     * Genera el cuadrante automático del mes unificando Adjuntos y Residentes.
     * Devuelve null si se generó con éxito o un mensaje de error si hubo colapso/validación.
     *
     * @param array<string, mixed> $params
     */
    public function generarCuadrante(int $especialidadId, string $colectivo, array $params): ?string
    {
        $resultado = $this->calcularCuadranteInterno($especialidadId, $colectivo, $params, true);
        return $resultado['error'];
    }

    /**
     * Motor interno de generación/simulación de cuadrantes (F3-01 y F3-02).
     *
     * @param array<string, mixed> $params
     * @return array{
     *   ok: bool,
     *   error: string|null,
     *   resumen: array<string, int|string>,
     *   alertas_relajacion: array<int, array{fecha: string, fecha_formateada: string, user_id: int, facultativo: string, motivo: string}>,
     *   guardias_simuladas: array<int, array<string, mixed>>,
     *   equidad_simulada: array<int, array<string, mixed>>
     * }
     */
    private function calcularCuadranteInterno(int $especialidadId, string $colectivo, array $params, bool $persistir): array
    {
        $mes = (int) $params['mes'];
        $anio = (int) $params['anio'];
        $personasPorDia = (int) $params['personas_por_dia'];
        $usarPlantillaCompleta = (bool) ($params['usar_plantilla_completa'] ?? true);
        /** @var array<int, int|string> $medicosIncluidos */
        $medicosIncluidos = is_array($params['medicos_incluidos'] ?? null) ? $params['medicos_incluidos'] : [];
        $respetarSalientes = (bool) ($params['respetar_salientes'] ?? true);
        $distanciaMinimaDias = (int) ($params['distancia_minima_dias'] ?? 1);
        $protegerVisperaVacaciones = (bool) ($params['proteger_vispera_vacaciones'] ?? true);
        $maxGuardiasMes = (int) ($params['max_guardias_mes'] ?? 0);
        $maxFindesMes = (int) ($params['max_findes_mes'] ?? 0);
        $maxDiariasMes = (int) ($params['max_diarias_mes'] ?? 0);
        $prorratearAusencias = (bool) ($params['prorratear_ausencias'] ?? true);
        $usarMemoriaAnual = (bool) ($params['usar_memoria_anual'] ?? true);
        $agrupacion = (string) ($params['agrupacion'] ?? 'ninguna');
        $modalidadParam = (string) ($params['modalidad'] ?? 'presencial');
        $modalidadTurno = in_array($modalidadParam, ['presencial', 'localizada'], true)
            ? $modalidadParam
            : 'presencial';

        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
        $diasDelMes = $inicioMes->daysInMonth;

        // Todos los facultativos del colectivo (para contabilizar guardias manuales incluso de médicos excluidos)
        $todosMedicosColectivo = $this->obtenerMedicosColectivo($especialidadId, $colectivo);
        $idsColectivoCompleto = $todosMedicosColectivo->pluck('id')->toArray();

        // F4-02: Si usar_plantilla_completa === false, filtrar siempre por $medicosIncluidos (incluso si llega vacío)
        // F5-02: Excluir automáticamente del sorteo automático a los facultativos con exento_guardias === true
        $medicos = $todosMedicosColectivo
            ->filter(fn(User $m): bool => !(bool) ($m->exento_guardias ?? false))
            ->when(!$usarPlantillaCompleta, function (Collection $col) use ($medicosIncluidos) {
                return $col->whereIn('id', $medicosIncluidos);
            })
            ->shuffle()
            ->values();

        if ($medicos->count() < $personasPorDia) {
            return [
                'ok' => false,
                'error' => 'Imposible generar cuadrante: No hay suficientes facultativos activos seleccionados.',
                'resumen' => [
                    'mes' => $mes,
                    'anio' => $anio,
                    'colectivo' => $colectivo,
                    'turnos_nuevos' => 0,
                    'manuales_respetados' => 0,
                    'total_alertas_relajacion' => 0,
                    'total_alertas_tope_legal' => 0,
                ],
                'alertas_relajacion' => [],
                'alertas_tope_legal' => [],
                'guardias_simuladas' => [],
                'equidad_simulada' => [],
            ];
        }

        $idsGrupo = $medicos->pluck('id')->toArray();
        $margenFrontera = max($distanciaMinimaDias, 5);

        $ausencias = Ausencia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsGrupo)
            ->where('estado', 'aprobada')
            ->where('tipo', '!=', 'rotacion_con_guardias')
            ->where('fecha_inicio', '<=', $finMes->copy()->addDays($margenFrontera))
            ->where('fecha_fin', '>=', $inicioMes->copy()->subDays($margenFrontera))
            ->get();

        $limitaciones = LimitacionGuardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsGrupo)
            ->get();

        // Guardias manuales de todo el colectivo (incluyendo médicos excluidos cuando usar_plantilla_completa es false)
        $guardiasManualesColectivo = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivoCompleto)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->where('is_manual', true)
            ->get();

        $idsExcluidos = array_values(array_diff($idsColectivoCompleto, $idsGrupo));
        $guardiasNoManualesExcluidos = !empty($idsExcluidos)
            ? Guardia::where('especialidad_id', $especialidadId)
                ->whereIn('user_id', $idsExcluidos)
                ->whereMonth('fecha', $mes)
                ->whereYear('fecha', $anio)
                ->where('is_manual', false)
                ->get()
            : collect();

        $guardiasManualesGrupo = $guardiasManualesColectivo->whereIn('user_id', $idsGrupo);

        // Frontera de distancia_minima_dias: mes anterior y mes siguiente
        $guardiasFrontera = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsGrupo)
            ->where(function ($q) use ($inicioMes, $finMes, $margenFrontera) {
                $q->where(function ($sub) use ($inicioMes, $margenFrontera) {
                    $sub->whereDate('fecha', '>=', $inicioMes->copy()->subDays($margenFrontera)->format('Y-m-d'))
                        ->whereDate('fecha', '<=', $inicioMes->copy()->subDay()->format('Y-m-d'));
                })->orWhere(function ($sub) use ($finMes, $margenFrontera) {
                    $sub->whereDate('fecha', '>=', $finMes->copy()->addDay()->format('Y-m-d'))
                        ->whereDate('fecha', '<=', $finMes->copy()->addDays($margenFrontera)->format('Y-m-d'));
                });
            })
            ->get();

        // 1. MAPA DE FESTIVOS DEL AÑO COMPLETO (NORMALES Y ESPECIALES)
        $mapaFestivosAnio = $this->obtenerMapaFestivos($especialidadId, $anio);

        $esFiestaOficial = fn(Carbon $date): bool => isset($mapaFestivosAnio[$date->format('Y-m-d')]);
        $esFiestaEspecial = fn(Carbon $date): bool => !empty($mapaFestivosAnio[$date->format('Y-m-d')]['es_especial']);
        $esFindeOFiesta = fn(Carbon $date): bool => $date->isWeekend() || $esFiestaOficial($date);
        $esFindePuro = fn(Carbon $date): bool => $date->isWeekend() && !$esFiestaOficial($date);

        // 2. PREINDEXACIÓN EN MEMORIA POR [user_id][fecha] (F2-02)
        /** @var array<int, array<string, bool>> $mapaAusenciaOLimitacion */
        $mapaAusenciaOLimitacion = [];
        /** @var array<int, array<string, bool>> $mapaVisperaVacaciones */
        $mapaVisperaVacaciones = [];
        /** @var array<int, array<string, bool>> $mapaFechasOcupadas */
        $mapaFechasOcupadas = [];
        /** @var array<string, int> $conteoCubiertosPorFecha */
        $conteoCubiertosPorFecha = [];

        $ausenciasPorMedico = $ausencias->groupBy('user_id');
        $limitacionesPorMedico = $limitaciones->groupBy('user_id');

        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $mapaAusenciaOLimitacion[$mId] = [];
            $mapaVisperaVacaciones[$mId] = [];
            $mapaFechasOcupadas[$mId] = [];

            $misAusencias = $ausenciasPorMedico->get($mId, collect());
            $misLimitaciones = $limitacionesPorMedico->get($mId, collect());

            for ($d = 1; $d <= $diasDelMes; $d++) {
                $fecha = Carbon::createFromDate($anio, $mes, $d)->startOfDay();
                $fechaStr = $fecha->format('Y-m-d');

                if ($this->estaBloqueadoPorAusenciaOLimitacion($fecha, $misAusencias, $misLimitaciones)) {
                    $mapaAusenciaOLimitacion[$mId][$fechaStr] = true;
                }

                if ($this->esVisperaDeVacaciones($fecha, $misAusencias)) {
                    $mapaVisperaVacaciones[$mId][$fechaStr] = true;
                }
            }
        }

        // Preindexar cobertura existente del día (incluyendo manuales de médicos excluidos)
        foreach ($guardiasManualesColectivo as $gm) {
            $fStr = Carbon::parse($gm->fecha)->format('Y-m-d');
            $conteoCubiertosPorFecha[$fStr] = ($conteoCubiertosPorFecha[$fStr] ?? 0) + 1;
            $uId = (int) $gm->user_id;
            if (isset($mapaFechasOcupadas[$uId])) {
                $mapaFechasOcupadas[$uId][$fStr] = true;
            }
        }

        foreach ($guardiasNoManualesExcluidos as $ge) {
            $fStr = Carbon::parse($ge->fecha)->format('Y-m-d');
            $conteoCubiertosPorFecha[$fStr] = ($conteoCubiertosPorFecha[$fStr] ?? 0) + 1;
        }

        foreach ($guardiasFrontera as $gf) {
            $fStr = Carbon::parse($gf->fecha)->format('Y-m-d');
            $uId = (int) $gf->user_id;
            if (isset($mapaFechasOcupadas[$uId])) {
                $mapaFechasOcupadas[$uId][$fStr] = true;
            }
        }

        // 3. INICIALIZACIÓN DE ESTADÍSTICAS Y MEMORIA ANUAL DE FESTIVOS
        /** @var array<int, array<string, int|float>> $stats */
        $stats = [];

        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $diasDisp = $prorratearAusencias
                ? ($diasDelMes - count($mapaAusenciaOLimitacion[$mId]))
                : $diasDelMes;
            $porcentajeJornada = max(10, min(100, (int) ($m->porcentaje_jornada ?? 100)));
            $factorJornada = $porcentajeJornada / 100.0;

            $stats[$mId] = [
                'total_mes' => 0,
                'findes_puros_mes' => 0,
                'findes_mes' => 0,
                'festivos_mes' => 0,
                'especiales_mes' => 0,
                'diarias_mes' => 0,
                'puntos_esfuerzo' => 0,
                'total_anual' => 0,
                'findes_anual' => 0,
                'festivos_anual' => 0,
                'especiales_anual' => 0,
                'especiales_anio_anterior' => 0,
                'puntos_esfuerzo_anual' => 0,
                'dias_disponibles_mes' => $diasDisp,
                'porcentaje_jornada' => $porcentajeJornada,
                'factor_jornada' => $factorJornada,
            ];
        }

        $historicoAnual = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsGrupo)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', '!=', $mes)
            ->get();

        /** @var array<int, string> $primeraFechaAnualPorMedico */
        $primeraFechaAnualPorMedico = [];

        foreach ($historicoAnual as $h) {
            $uId = (int) $h->user_id;
            if (!isset($stats[$uId])) {
                continue;
            }
            $infoH = $this->clasificarTipologiaGuardia($h, $mapaFestivosAnio);
            $fStr = $infoH['fecha_str'];
            if (!isset($primeraFechaAnualPorMedico[$uId]) || $fStr < $primeraFechaAnualPorMedico[$uId]) {
                $primeraFechaAnualPorMedico[$uId] = $fStr;
            }
            $stats[$uId]['total_anual']++;
            $stats[$uId]['puntos_esfuerzo_anual'] += ($infoH['es_24h'] ? 2 : 1);
            if ($infoH['es_24h']) {
                $stats[$uId]['findes_anual']++;
            }
            if ($infoH['es_festivo_oficial']) {
                if ($infoH['es_festivo_especial']) {
                    $stats[$uId]['especiales_anual']++;
                } else {
                    $stats[$uId]['festivos_anual']++;
                }
            }
        }

        // Normalización YTD por días activos ponderados (sin depender de created_at)
        $inicioAnio = Carbon::createFromDate($anio, 1, 1)->startOfDay();
        $diasTranscurridosAnio = $mes > 1 ? max(1, (int) $inicioAnio->diffInDays($inicioMes)) : 0;
        $ausenciasAnioPorMedico = collect();
        if ($mes > 1 && !empty($idsGrupo)) {
            $ausenciasAnioPorMedico = Ausencia::where('especialidad_id', $especialidadId)
                ->whereIn('user_id', $idsGrupo)
                ->where('estado', 'aprobada')
                ->where('tipo', '!=', 'rotacion_con_guardias')
                ->where('fecha_inicio', '<', $inicioMes->toDateString())
                ->where('fecha_fin', '>=', $inicioAnio->toDateString())
                ->get()
                ->groupBy('user_id');
        }

        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $factorJ = max(0.1, (float) ($stats[$mId]['factor_jornada'] ?? 1.0));
            $stats[$mId]['tasa_puntos_anual'] = 0.0;
            $stats[$mId]['tasa_findes_anual'] = 0.0;
            $stats[$mId]['ratio_actividad_anio'] = 0.0;
            $stats[$mId]['festivos_comparables'] = (float) $stats[$mId]['festivos_anual'];
            $stats[$mId]['especiales_comparables'] = (float) $stats[$mId]['especiales_anual'];

            if ($mes <= 1 || $stats[$mId]['total_anual'] === 0 || !isset($primeraFechaAnualPorMedico[$mId])) {
                continue;
            }

            $inicioActivo = Carbon::parse($primeraFechaAnualPorMedico[$mId])->startOfMonth();
            if ($inicioActivo->lt($inicioAnio)) {
                $inicioActivo = $inicioAnio->copy();
            }
            $finPrevioMes = $inicioMes->copy()->subDay()->startOfDay();
            $diasDesdeInicio = max(1, (int) $inicioActivo->diffInDays($inicioMes));

            $diasAusenciaAnio = 0;
            foreach ($ausenciasAnioPorMedico->get($mId, collect()) as $ausAnt) {
                $aIni = Carbon::parse($ausAnt->fecha_inicio)->startOfDay();
                $aFin = Carbon::parse($ausAnt->fecha_fin)->startOfDay();
                if ($aIni->lt($inicioActivo)) {
                    $aIni = $inicioActivo->copy();
                }
                if ($aFin->gt($finPrevioMes)) {
                    $aFin = $finPrevioMes->copy();
                }
                if ($aIni->lte($aFin)) {
                    $diasAusenciaAnio += ((int) $aIni->diffInDays($aFin) + 1);
                }
            }

            $diasActivosBrutos = max(15, $diasDesdeInicio - $diasAusenciaAnio);
            $diasActivosPonderados = max(15.0, $diasActivosBrutos * $factorJ);
            $ratioActividad = min(1.0, $diasActivosPonderados / max(1, $diasTranscurridosAnio));

            $stats[$mId]['ratio_actividad_anio'] = $ratioActividad;
            $stats[$mId]['tasa_puntos_anual'] = ($stats[$mId]['puntos_esfuerzo_anual'] / $diasActivosPonderados) * 30.0;
            $stats[$mId]['tasa_findes_anual'] = ($stats[$mId]['findes_anual'] / $diasActivosPonderados) * 30.0;
        }

        // F6-06: Rotación interanual de festivos [ESPECIAL] (Navidad, Nochebuena, Nochevieja, Año Nuevo, etc.)
        if ($usarMemoriaAnual) {
            $mapaFestivosAnioAnterior = $this->obtenerMapaFestivos($especialidadId, $anio - 1);
            $fechasEspecialesAnioAnterior = array_keys(
                array_filter($mapaFestivosAnioAnterior, fn(array $f): bool => (bool) $f['es_especial'])
            );

            if (!empty($fechasEspecialesAnioAnterior)) {
                $guardiasEspecialesAnioAnt = Guardia::where('especialidad_id', $especialidadId)
                    ->whereIn('user_id', $idsGrupo)
                    ->whereYear('fecha', $anio - 1)
                    ->get();

                $setFechasEspAnt = array_flip($fechasEspecialesAnioAnterior);
                foreach ($guardiasEspecialesAnioAnt as $gea) {
                    $uId = (int) $gea->user_id;
                    $fStrAnt = Carbon::parse($gea->fecha)->format('Y-m-d');
                    if (isset($stats[$uId], $setFechasEspAnt[$fStrAnt])) {
                        $stats[$uId]['especiales_anio_anterior']++;
                    }
                }
            }
        }

        // Hándicap de Media e Imputación Proporcional en Festivos para Nuevas Incorporaciones o Bajas Largas
        $veteranos = collect($stats)->filter(fn(array $s): bool => $s['total_anual'] > 0);
        if ($veteranos->isNotEmpty()) {
            $mediaPuntosAnual = (float) $veteranos->avg('puntos_esfuerzo_anual');
            $mediaFindesAnual = (float) $veteranos->avg('findes_anual');
            $mediaTasaPuntos = (float) $veteranos->avg('tasa_puntos_anual');
            $mediaTasaFindes = (float) $veteranos->avg('tasa_findes_anual');
            $mediaFestivosAnual = (float) $veteranos->avg('festivos_anual');
            $mediaEspecialesAnual = (float) $veteranos->avg('especiales_anual');

            foreach ($medicos as $m) {
                $mId = (int) $m->id;
                if ($stats[$mId]['total_anual'] === 0) {
                    $stats[$mId]['puntos_esfuerzo_anual'] = round($mediaPuntosAnual, 1);
                    $stats[$mId]['findes_anual'] = round($mediaFindesAnual, 1);
                    $stats[$mId]['tasa_puntos_anual'] = round($mediaTasaPuntos, 2);
                    $stats[$mId]['tasa_findes_anual'] = round($mediaTasaFindes, 2);
                    $stats[$mId]['festivos_anual'] = (int) floor($mediaFestivosAnual);
                    $stats[$mId]['especiales_anual'] = (int) floor($mediaEspecialesAnual);
                    $stats[$mId]['festivos_comparables'] = $mediaFestivosAnual;
                    $stats[$mId]['especiales_comparables'] = $mediaEspecialesAnual;
                } else {
                    $ratioAct = (float) ($stats[$mId]['ratio_actividad_anio'] ?? 1.0);
                    $stats[$mId]['festivos_comparables'] = (float) $stats[$mId]['festivos_anual'] + ($mediaFestivosAnual * (1.0 - $ratioAct));
                    $stats[$mId]['especiales_comparables'] = (float) $stats[$mId]['especiales_anual'] + ($mediaEspecialesAnual * (1.0 - $ratioAct));
                }
            }
        }

        // 4. CÁLCULO EXACTO DE CUOTA DE FINES DE SEMANA UNIFICADO (F2-01)
        $totalSlotsFindePuro = 0;
        /** @var array<int, int> $diasFindeDisponiblesPorMedico */
        $diasFindeDisponiblesPorMedico = array_fill_keys($idsGrupo, 0);

        for ($d = 1; $d <= $diasDelMes; $d++) {
            $f = Carbon::createFromDate($anio, $mes, $d)->startOfDay();
            if ($esFindePuro($f)) {
                $totalSlotsFindePuro += $personasPorDia;
                $fStr = $f->format('Y-m-d');

                foreach ($medicos as $m) {
                    $mId = (int) $m->id;
                    if (empty($mapaAusenciaOLimitacion[$mId][$fStr])) {
                        $diasFindeDisponiblesPorMedico[$mId]++;
                    }
                }
            }
        }

        /** @var array<int, int|float> $statsPesoFinde */
        $statsPesoFinde = [];
        $sumaPesosFinde = 0;
        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $factorJ = (float) ($stats[$mId]['factor_jornada'] ?? 1.0);
            $pesoBase = $diasFindeDisponiblesPorMedico[$mId] > 0
                ? ($prorratearAusencias ? $stats[$mId]['dias_disponibles_mes'] : $diasDelMes)
                : 0;
            $pesoM = $pesoBase * $factorJ;
            $statsPesoFinde[$mId] = $pesoM;
            $sumaPesosFinde += $pesoM;
        }

        /** @var array<int, int> $cuotaExactaFinde */
        $cuotaExactaFinde = [];
        /** @var array<int, float> $restosFinde */
        $restosFinde = [];
        $sumaCuotasAsignadas = 0;

        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $pesoM = $statsPesoFinde[$mId] ?? 0;
            if ($pesoM <= 0 || $sumaPesosFinde <= 0) {
                $cuotaExactaFinde[$mId] = 0;
                $restosFinde[$mId] = -1.0;
                continue;
            }
            $exacto = $totalSlotsFindePuro * ($pesoM / $sumaPesosFinde);
            $base = (int) min($diasFindeDisponiblesPorMedico[$mId], floor($exacto));
            $cuotaExactaFinde[$mId] = $base;
            $desempateAnual = $usarMemoriaAnual ? ($stats[$mId]['tasa_findes_anual'] * 0.001) : 0.0;
            $restosFinde[$mId] = ($exacto - $base) - $desempateAnual;
            $sumaCuotasAsignadas += $base;
        }

        $faltanPorRepartir = max(0, $totalSlotsFindePuro - $sumaCuotasAsignadas);
        arsort($restosFinde);
        foreach (array_keys($restosFinde) as $mId) {
            if ($faltanPorRepartir <= 0) {
                break;
            }
            if ($restosFinde[$mId] >= 0 && $cuotaExactaFinde[$mId] < $diasFindeDisponiblesPorMedico[$mId]) {
                $cuotaExactaFinde[$mId]++;
                $faltanPorRepartir--;
            }
        }

        // Computar guardias manuales ya fijadas en el mes para los médicos del grupo
        foreach ($guardiasManualesGrupo as $gm) {
            $uId = (int) $gm->user_id;
            if (!isset($stats[$uId])) {
                continue;
            }
            $f = Carbon::parse($gm->fecha);
            $stats[$uId]['total_mes']++;
            if ($esFindeOFiesta($f) || $gm->tipo === 'festivo_24h') {
                $stats[$uId]['findes_mes']++;
                $stats[$uId]['puntos_esfuerzo'] += 2;
                if ($esFiestaEspecial($f)) {
                    $stats[$uId]['especiales_mes']++;
                    $stats[$uId]['especiales_anual']++;
                } elseif ($esFiestaOficial($f)) {
                    $stats[$uId]['festivos_mes']++;
                    $stats[$uId]['festivos_anual']++;
                } else {
                    $stats[$uId]['findes_puros_mes']++;
                }
            } else {
                $stats[$uId]['diarias_mes']++;
                $stats[$uId]['puntos_esfuerzo'] += 1;
            }
        }

        // Precalcular horas de la semana ISO de frontera del mes anterior y manuales ya fijadas para penalización suave >48h/sem
        /** @var array<int, array<string, int>> $horasSemanaIsoMesAnterior */
        $horasSemanaIsoMesAnterior = [];
        $inicioSemanaPrimerDiaMes = $inicioMes->copy()->startOfWeek(Carbon::MONDAY);
        if ($inicioSemanaPrimerDiaMes->lt($inicioMes)) {
            $mapaFestivosMesAnt = (int) $inicioSemanaPrimerDiaMes->year === $anio
                ? $mapaFestivosAnio
                : $this->obtenerMapaFestivos($especialidadId, (int) $inicioSemanaPrimerDiaMes->year);

            $guardiasSemanaIsoAnt = Guardia::where('especialidad_id', $especialidadId)
                ->whereIn('user_id', $idsGrupo)
                ->whereDate('fecha', '>=', $inicioSemanaPrimerDiaMes->toDateString())
                ->whereDate('fecha', '<', $inicioMes->toDateString())
                ->get();

            foreach ($guardiasSemanaIsoAnt as $gaAnt) {
                $uIdAnt = (int) $gaAnt->user_id;
                $infoAnt = $this->clasificarTipologiaGuardia($gaAnt, $mapaFestivosMesAnt);
                $semKeyAnt = Carbon::parse($gaAnt->fecha)->format('o-\WW');
                $horasSemanaIsoMesAnterior[$uIdAnt][$semKeyAnt] = ($horasSemanaIsoMesAnterior[$uIdAnt][$semKeyAnt] ?? 0)
                    + (int) $infoAnt['horas_efectivas_computo'];
            }
        }

        /** @var array<int, array<string, int>> $horasSemanalesEnCurso */
        $horasSemanalesEnCurso = $horasSemanaIsoMesAnterior;
        foreach ($guardiasManualesGrupo as $gm) {
            $uId = (int) $gm->user_id;
            if (!isset($stats[$uId])) {
                continue;
            }
            $infoGm = $this->clasificarTipologiaGuardia($gm, $mapaFestivosAnio);
            $semKeyGm = Carbon::parse($gm->fecha)->format('o-\WW');
            $horasSemanalesEnCurso[$uId][$semKeyGm] = ($horasSemanalesEnCurso[$uId][$semKeyGm] ?? 0)
                + (int) $infoGm['horas_efectivas_computo'];
        }

        /** @var array<int, array<string, mixed>> $guardiasAInsertar */
        $guardiasAInsertar = [];

        // VALIDADOR DE RESTRICCIONES DURAS CON MAPAS PREINDEXADOS O(1)
        $esElegible = function (int $medicoId, array $fechasAAsignar, bool $relajarTopes = false) use (
            &$stats,
            &$mapaAusenciaOLimitacion,
            &$mapaVisperaVacaciones,
            &$mapaFechasOcupadas,
            $esFindeOFiesta,
            $respetarSalientes,
            $distanciaMinimaDias,
            $protegerVisperaVacaciones,
            $maxGuardiasMes,
            $maxFindesMes,
            $maxDiariasMes
        ): bool {
            if ($stats[$medicoId]['dias_disponibles_mes'] <= 0) {
                return false;
            }

            $nFindes = 0;
            $nDiarias = 0;
            foreach ($fechasAAsignar as $f) {
                if ($esFindeOFiesta($f)) {
                    $nFindes++;
                } else {
                    $nDiarias++;
                }
            }

            if (!$relajarTopes) {
                $factorJ = (float) ($stats[$medicoId]['factor_jornada'] ?? 1.0);
                $topeGuardiasMedico = $maxGuardiasMes > 0
                    ? max(1, (int) round($maxGuardiasMes * $factorJ))
                    : 0;
                $topeFindesMedico = $maxFindesMes > 0
                    ? max(1, (int) round($maxFindesMes * $factorJ))
                    : 0;
                $topeDiariasMedico = $maxDiariasMes > 0
                    ? max(1, (int) round($maxDiariasMes * $factorJ))
                    : 0;

                if ($topeGuardiasMedico > 0 && ($stats[$medicoId]['total_mes'] + count($fechasAAsignar)) > $topeGuardiasMedico) {
                    return false;
                }
                if ($topeFindesMedico > 0 && ($stats[$medicoId]['findes_mes'] + $nFindes) > $topeFindesMedico) {
                    return false;
                }
                if ($topeDiariasMedico > 0 && ($stats[$medicoId]['diarias_mes'] + $nDiarias) > $topeDiariasMedico) {
                    return false;
                }
            }

            $fechasBloqueSet = [];
            foreach ($fechasAAsignar as $f) {
                $fechasBloqueSet[$f->format('Y-m-d')] = true;
            }

            foreach ($fechasAAsignar as $fecha) {
                $fechaStr = $fecha->format('Y-m-d');

                if (!empty($mapaFechasOcupadas[$medicoId][$fechaStr])) {
                    return false;
                }

                if (!empty($mapaAusenciaOLimitacion[$medicoId][$fechaStr])) {
                    return false;
                }

                if ($protegerVisperaVacaciones && !empty($mapaVisperaVacaciones[$medicoId][$fechaStr])) {
                    return false;
                }

                if ($respetarSalientes && $distanciaMinimaDias > 0) {
                    for ($offset = 1; $offset <= $distanciaMinimaDias; $offset++) {
                        $prevStr = $fecha->copy()->subDays($offset)->format('Y-m-d');
                        if (!isset($fechasBloqueSet[$prevStr]) && !empty($mapaFechasOcupadas[$medicoId][$prevStr])) {
                            return false;
                        }

                        $nextStr = $fecha->copy()->addDays($offset)->format('Y-m-d');
                        if (!isset($fechasBloqueSet[$nextStr]) && !empty($mapaFechasOcupadas[$medicoId][$nextStr])) {
                            return false;
                        }
                    }

                    // Bloque B: Traslado de saliente en domingo al lunes cuando hace guardia en sábado
                    if ((int) $fecha->dayOfWeekIso === 1) {
                        $sabadoPrevioStr = $fecha->copy()->subDays(2)->format('Y-m-d');
                        if (!isset($fechasBloqueSet[$sabadoPrevioStr]) && !empty($mapaFechasOcupadas[$medicoId][$sabadoPrevioStr])) {
                            return false;
                        }
                    }

                    if ((int) $fecha->dayOfWeekIso === 6) {
                        $lunesSiguienteStr = $fecha->copy()->addDays(2)->format('Y-m-d');
                        if (!isset($fechasBloqueSet[$lunesSiguienteStr]) && !empty($mapaFechasOcupadas[$medicoId][$lunesSiguienteStr])) {
                            return false;
                        }
                    }
                }
            }

            return true;
        };

        $getBestDoctorForBlock = function (array $fechasBloque, bool $forzarSinTopes = false, bool $exigirCuotaFindeCompleta = false) use (
            $medicos,
            $esElegible,
            &$stats,
            &$horasSemanalesEnCurso,
            $cuotaExactaFinde,
            $usarMemoriaAnual,
            $esFindeOFiesta,
            $esFiestaOficial,
            $esFiestaEspecial,
            $esFindePuro
        ): ?User {
            $candidatos = $medicos->filter(function (User $m) use (
                $fechasBloque,
                $esElegible,
                $forzarSinTopes,
                $exigirCuotaFindeCompleta,
                &$stats,
                $cuotaExactaFinde,
                $esFindePuro
            ): bool {
                $mId = (int) $m->id;
                if (!$esElegible($mId, $fechasBloque, $forzarSinTopes)) {
                    return false;
                }

                if ($exigirCuotaFindeCompleta) {
                    $diasFindePuroEnBloque = 0;
                    foreach ($fechasBloque as $f) {
                        if ($esFindePuro($f)) {
                            $diasFindePuroEnBloque++;
                        }
                    }
                    $minimoExigido = max(count($fechasBloque) >= 2 && $diasFindePuroEnBloque >= 2 ? 2 : 1, $diasFindePuroEnBloque);
                    $cupoRestanteMedico = $cuotaExactaFinde[$mId] - $stats[$mId]['findes_puros_mes'];
                    if ($cupoRestanteMedico < $minimoExigido) {
                        return false;
                    }
                }

                return true;
            });

            if ($candidatos->isEmpty()) {
                return null;
            }

            $candidatosPuntuados = $candidatos->map(function (User $m) use (
                $fechasBloque,
                &$stats,
                &$horasSemanalesEnCurso,
                $cuotaExactaFinde,
                $usarMemoriaAnual,
                $esFindeOFiesta,
                $esFiestaOficial,
                $esFiestaEspecial,
                $esFindePuro,
                $exigirCuotaFindeCompleta
            ): array {
                $mId = (int) $m->id;
                $n24h = 0;
                $nFindePuro = 0;
                $nFestivoNormal = 0;
                $nFestivoEspecial = 0;
                $horasPorSemanaBloque = [];
                foreach ($fechasBloque as $f) {
                    $es24Dia = $esFindeOFiesta($f);
                    if ($es24Dia) {
                        $n24h++;
                    }
                    $semKey = $f->format('o-\WW');
                    $horasPorSemanaBloque[$semKey] = ($horasPorSemanaBloque[$semKey] ?? 0) + ($es24Dia ? 24 : 17);

                    if ($esFiestaEspecial($f)) {
                        $nFestivoEspecial++;
                    } elseif ($esFiestaOficial($f)) {
                        $nFestivoNormal++;
                    } elseif ($esFindePuro($f)) {
                        $nFindePuro++;
                    }
                }

                $diasDisp = max(1, (int) $stats[$mId]['dias_disponibles_mes']);
                $factorJ = max(0.1, (float) ($stats[$mId]['factor_jornada'] ?? 1.0));
                $diasDispPonderados = max(0.5, $diasDisp * $factorJ);
                $score = 0.0;

                if ($nFestivoEspecial > 0) {
                    if ($usarMemoriaAnual) {
                        $score += ((float) ($stats[$mId]['especiales_comparables'] ?? $stats[$mId]['especiales_anual']) * 5000000);
                        $score += (($stats[$mId]['especiales_anio_anterior'] ?? 0) * 2500000);
                        $score += ((float) ($stats[$mId]['festivos_comparables'] ?? $stats[$mId]['festivos_anual']) * 500000);
                    } else {
                        $score += ($stats[$mId]['especiales_mes'] * 5000000);
                        $score += ($stats[$mId]['festivos_mes'] * 500000);
                    }
                }

                if ($nFestivoNormal > 0) {
                    if ($usarMemoriaAnual) {
                        $score += ((float) ($stats[$mId]['festivos_comparables'] ?? $stats[$mId]['festivos_anual']) * 3000000);
                        $score += ((float) ($stats[$mId]['especiales_comparables'] ?? $stats[$mId]['especiales_anual']) * 500000);
                    } else {
                        $score += ($stats[$mId]['festivos_mes'] * 3000000);
                        $score += ($stats[$mId]['especiales_mes'] * 500000);
                    }
                }

                if ($nFindePuro > 0) {
                    $futurosFindesPuros = $stats[$mId]['findes_puros_mes'] + $nFindePuro;
                    $excesoSobreCuota = max(0, $futurosFindesPuros - $cuotaExactaFinde[$mId]);
                    $score += ($excesoSobreCuota * 4000000);

                    if (!$exigirCuotaFindeCompleta && count($fechasBloque) === 1) {
                        $cupoRestante = $cuotaExactaFinde[$mId] - $stats[$mId]['findes_puros_mes'];
                        if ($cupoRestante >= 2) {
                            $score += 800000;
                        }
                    }

                    $score += ($stats[$mId]['findes_puros_mes'] * 300000);
                }

                // Penalización suave (no bloqueante) si la asignación supera las 48h en esa semana ISO
                foreach ($horasPorSemanaBloque as $semKey => $hBloqueSem) {
                    $totalSem = (int) ($horasSemanalesEnCurso[$mId][$semKey] ?? 0) + $hBloqueSem;
                    if ($totalSem > 48) {
                        $score += 200000;
                    }
                }

                $puntosNuevos = ($n24h * 2) + (count($fechasBloque) - $n24h);
                $ratioEsfuerzoMes = ($stats[$mId]['puntos_esfuerzo'] + $puntosNuevos) / $diasDispPonderados;
                $score += ($ratioEsfuerzoMes * 100000);

                if ($usarMemoriaAnual) {
                    $mediaGrupoTasaPuntos = (float) (collect($stats)->avg('tasa_puntos_anual') ?: 1);
                    $mediaGrupoTasaFindes = (float) (collect($stats)->avg('tasa_findes_anual') ?: 1);

                    $desviacionPuntos = max(-4.0, min(4.0, (float) ($stats[$mId]['tasa_puntos_anual'] ?? 0) - $mediaGrupoTasaPuntos));
                    $desviacionFindes = max(-2.0, min(2.0, (float) ($stats[$mId]['tasa_findes_anual'] ?? 0) - $mediaGrupoTasaFindes));

                    $score += ($desviacionPuntos * 2500);
                    if ($n24h > 0) {
                        $score += ($desviacionFindes * 6000);
                    }
                }

                return ['medico' => $m, 'score' => $score + (mt_rand(0, 99) / 1000)];
            });

            $mejor = $candidatosPuntuados->sortBy('score')->first();
            return $mejor ? $mejor['medico'] : null;
        };

        $asignarBloque = function (User $medico, array $fechas, string $motivo) use (
            &$guardiasAInsertar,
            &$stats,
            &$mapaFechasOcupadas,
            &$conteoCubiertosPorFecha,
            &$horasSemanalesEnCurso,
            $especialidadId,
            $modalidadTurno,
            $esFindeOFiesta,
            $esFiestaOficial,
            $esFiestaEspecial,
            $esFindePuro
        ): void {
            $mId = (int) $medico->id;
            foreach ($fechas as $fecha) {
                $fechaStr = $fecha->format('Y-m-d');
                $es24Dia = $esFindeOFiesta($fecha);
                $tipoTurno = $es24Dia ? 'festivo_24h' : 'diaria_17h';
                $semKey = $fecha->format('o-\WW');
                $horasSemanalesEnCurso[$mId][$semKey] = ($horasSemanalesEnCurso[$mId][$semKey] ?? 0) + ($es24Dia ? 24 : 17);
                $guardiasAInsertar[] = [
                    'especialidad_id' => $especialidadId,
                    'user_id' => $mId,
                    'fecha' => $fechaStr,
                    'tipo' => $tipoTurno,
                    'modalidad' => $modalidadTurno,
                    'estado' => 'programada',
                    'is_manual' => false,
                    'observaciones' => $motivo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $mapaFechasOcupadas[$mId][$fechaStr] = true;
                $conteoCubiertosPorFecha[$fechaStr] = ($conteoCubiertosPorFecha[$fechaStr] ?? 0) + 1;

                $stats[$mId]['total_mes']++;
                if ($esFindeOFiesta($fecha)) {
                    $stats[$mId]['findes_mes']++;
                    $stats[$mId]['puntos_esfuerzo'] += 2;
                    if ($esFiestaEspecial($fecha)) {
                        $stats[$mId]['especiales_mes']++;
                        $stats[$mId]['especiales_anual']++;
                    } elseif ($esFiestaOficial($fecha)) {
                        $stats[$mId]['festivos_mes']++;
                        $stats[$mId]['festivos_anual']++;
                    } elseif ($esFindePuro($fecha)) {
                        $stats[$mId]['findes_puros_mes']++;
                    }
                } else {
                    $stats[$mId]['diarias_mes']++;
                    $stats[$mId]['puntos_esfuerzo'] += 1;
                }
            }
        };

        $contarCubiertosDia = function (Carbon $f) use (&$conteoCubiertosPorFecha): int {
            return $conteoCubiertosPorFecha[$f->format('Y-m-d')] ?? 0;
        };

        /** @var array<int, array{fecha: string, fecha_formateada: string, user_id: int, facultativo: string, motivo: string}> $alertasRelajacion */
        $alertasRelajacion = [];

        $seleccionarCandidatoDia = function (Carbon $fecha, string $faseEtiqueta) use (
            $getBestDoctorForBlock,
            &$alertasRelajacion
        ): ?User {
            $candEstricto = $getBestDoctorForBlock([$fecha], false, false);
            if ($candEstricto !== null) {
                return $candEstricto;
            }

            $candRelajado = $getBestDoctorForBlock([$fecha], true, false);
            if ($candRelajado !== null) {
                $alertasRelajacion[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'fecha_formateada' => $fecha->format('d/m/Y'),
                    'user_id' => (int) $candRelajado->id,
                    'facultativo' => $candRelajado->name,
                    'motivo' => "Relajación de tope mensual ({$faseEtiqueta}) para cubrir el {$fecha->format('d/m/Y')}.",
                ];
            }

            return $candRelajado;
        };

        $errorColapso = null;

        // FASE 1A: FESTIVOS ESPECIALES [ESPECIAL]
        for ($d = 1; $d <= $diasDelMes && $errorColapso === null; $d++) {
            $fecha = Carbon::createFromDate($anio, $mes, $d);
            if ($esFiestaEspecial($fecha)) {
                while ($contarCubiertosDia($fecha) < $personasPorDia) {
                    $cand = $seleccionarCandidatoDia($fecha, 'Festivo Especial');
                    if ($cand) {
                        $asignarBloque($cand, [$fecha], 'IA (★ Festivo Especial)');
                    } else {
                        $errorColapso = 'COLAPSO EN FESTIVO ESPECIAL: Imposible cubrir el día ' . $fecha->format('d/m/Y') . '.';
                        break;
                    }
                }
            }
        }

        // FASE 1B: FESTIVOS ORDINARIOS
        for ($d = 1; $d <= $diasDelMes && $errorColapso === null; $d++) {
            $fecha = Carbon::createFromDate($anio, $mes, $d);
            if ($esFiestaOficial($fecha) && !$esFiestaEspecial($fecha)) {
                while ($contarCubiertosDia($fecha) < $personasPorDia) {
                    $cand = $seleccionarCandidatoDia($fecha, 'Festivo Oficial');
                    if ($cand) {
                        $asignarBloque($cand, [$fecha], 'IA (Festivo Anual)');
                    } else {
                        $errorColapso = 'COLAPSO EN FESTIVO: Imposible cubrir el festivo ' . $fecha->format('d/m/Y') . '.';
                        break;
                    }
                }
            }
        }

        // FASE 2: FINES DE SEMANA (1º Bloques completos para cuota >= 2, 2º Días sueltos)
        if ($errorColapso === null && $agrupacion !== 'ninguna') {
            $bloquesCandidatos = [];

            for ($d = 1; $d <= $diasDelMes; $d++) {
                $f = Carbon::createFromDate($anio, $mes, $d);
                if ($f->dayOfWeekIso === 5) {
                    $s = $f->copy()->addDay();
                    $dom = $f->copy()->addDays(2);
                    $bloque = [];
                    if ($agrupacion === 'v_s_d' && $s->month == $mes && $dom->month == $mes) {
                        $bloque = [$f, $s, $dom];
                    } elseif ($agrupacion === 's_d' && $s->month == $mes && $dom->month == $mes) {
                        $bloque = [$s, $dom];
                    } elseif ($agrupacion === 'v_s' && $s->month == $mes) {
                        $bloque = [$f, $s];
                    }
                    if (count($bloque) > 1) {
                        $bloquesCandidatos[] = $bloque;
                    }
                } elseif ($d === 1 && $f->dayOfWeekIso === 6 && in_array($agrupacion, ['s_d', 'v_s_d'], true)) {
                    $bloquesCandidatos[] = [$f, $f->copy()->addDay()];
                }
            }

            // PASO 2A: Asignar bloques completos a médicos con cuota >= 2
            foreach ($bloquesCandidatos as $fechasBloque) {
                for ($p = 0; $p < $personasPorDia; $p++) {
                    $diasNecesitanCobertura = array_values(array_filter($fechasBloque, fn(Carbon $fb): bool => $contarCubiertosDia($fb) < $personasPorDia));
                    if (count($diasNecesitanCobertura) < 2) {
                        continue;
                    }

                    $candAgrupado = $getBestDoctorForBlock($diasNecesitanCobertura, false, true);
                    if ($candAgrupado) {
                        $asignarBloque($candAgrupado, $diasNecesitanCobertura, 'IA (Bloque ' . strtoupper($agrupacion) . ')');
                    }
                }
            }
        }

        // PASO 2B: Rellenar Sábados y Domingos puros restantes de forma equitativa
        for ($d = 1; $d <= $diasDelMes && $errorColapso === null; $d++) {
            $fecha = Carbon::createFromDate($anio, $mes, $d);
            if ($esFindePuro($fecha)) {
                while ($contarCubiertosDia($fecha) < $personasPorDia) {
                    $cand = $seleccionarCandidatoDia($fecha, 'Fin de Semana');
                    if ($cand) {
                        $asignarBloque($cand, [$fecha], 'IA (Finde Equitativo)');
                    } else {
                        $errorColapso = 'COLAPSO EN FIN DE SEMANA: Imposible cubrir el día ' . $fecha->format('d/m/Y') . '.';
                        break;
                    }
                }
            }
        }

        // FASE 3: RELLENO DE DÍAS DIARIOS (17H) CON HEURÍSTICA MRV
        if ($errorColapso === null) {
            $diasPendientes = [];
            for ($dia = 1; $dia <= $diasDelMes; $dia++) {
                $fecha = Carbon::createFromDate($anio, $mes, $dia);
                if ($contarCubiertosDia($fecha) < $personasPorDia) {
                    $numElegibles = $medicos->filter(fn(User $m): bool => $esElegible((int) $m->id, [$fecha], false))->count();
                    $diasPendientes[] = ['fecha' => $fecha, 'elegibles' => $numElegibles];
                }
            }

            usort($diasPendientes, fn(array $a, array $b): int => $a['elegibles'] <=> $b['elegibles']);

            foreach ($diasPendientes as $itemDia) {
                $fecha = $itemDia['fecha'];
                while ($contarCubiertosDia($fecha) < $personasPorDia) {
                    $cand = $seleccionarCandidatoDia($fecha, 'Guardia Diaria');
                    if ($cand) {
                        $motivo = $esFindeOFiesta($fecha) ? 'IA (24h)' : 'IA (Diaria 17h)';
                        $asignarBloque($cand, [$fecha], $motivo);
                    } else {
                        $errorColapso = 'COLAPSO: Imposible cubrir el día ' . $fecha->format('d/m/Y') . '. Demasiadas bajas, salientes o topes estrictos.';
                        break 2;
                    }
                }
            }
        }

        // Construir vista previa (guardias_simuladas y equidad_simulada) para Dry-Run o retorno estructurado
        $mapaNombresMedicos = $todosMedicosColectivo->pluck('name', 'id')->toArray();
        $coleccionSimulada = collect();

        foreach ($guardiasManualesColectivo as $gm) {
            $coleccionSimulada->push($gm);
        }
        foreach ($guardiasNoManualesExcluidos as $ge) {
            $coleccionSimulada->push($ge);
        }
        foreach ($guardiasAInsertar as $idx => $gRow) {
            $tempGuardia = new Guardia([
                'id' => -1 * ($idx + 1),
                'especialidad_id' => $gRow['especialidad_id'],
                'user_id' => $gRow['user_id'],
                'fecha' => $gRow['fecha'],
                'tipo' => $gRow['tipo'],
                'modalidad' => $gRow['modalidad'] ?? 'presencial',
                'estado' => $gRow['estado'],
                'is_manual' => false,
                'observaciones' => $gRow['observaciones'],
            ]);
            $coleccionSimulada->push($tempGuardia);
        }

        $guardiasSimuladas = $coleccionSimulada
            ->sortBy(fn(Guardia $g): string => Carbon::parse($g->fecha)->format('Y-m-d'))
            ->values()
            ->map(function (Guardia $g) use ($mapaFestivosAnio, $mapaNombresMedicos): array {
                $info = $this->clasificarTipologiaGuardia($g, $mapaFestivosAnio);
                $fechaCarbon = Carbon::parse($g->fecha);
                $fechaCarbon->locale('es');

                return [
                    'id' => (int) ($g->id ?? 0),
                    'user_id' => (int) $g->user_id,
                    'facultativo' => $mapaNombresMedicos[(int) $g->user_id] ?? 'Desconocido',
                    'fecha' => $info['fecha_str'],
                    'fecha_formateada' => $fechaCarbon->format('d/m/Y'),
                    'dia_semana' => ucfirst($fechaCarbon->dayName),
                    'tipo' => $g->tipo,
                    'modalidad' => $info['modalidad'],
                    'horas' => $info['horas'],
                    'horas_efectivas' => $info['horas_efectivas_computo'],
                    'es_festivo_oficial' => $info['es_festivo_oficial'],
                    'es_festivo_especial' => $info['es_festivo_especial'],
                    'tipo_badge' => $info['etiqueta'],
                    'es_finde' => $info['es_24h'],
                    'is_manual' => (bool) $g->is_manual,
                    'observaciones' => $g->observaciones,
                ];
            })
            ->all();

        // F5-03 / F6-03: Evaluar tope horario legal (Directiva Europea 48h/semana) de forma estrictamente informativa,
        // considerando también los días de la misma semana ISO pertenecientes al mes anterior.
        /** @var array<int, array{user_id: int, facultativo: string, semana: string, horas_semana: int, motivo: string}> $alertasTopeLegal */
        $alertasTopeLegal = [];
        /** @var array<int, int> $horasEfectivasPorMedico */
        $horasEfectivasPorMedico = array_fill_keys($idsGrupo, 0);
        /** @var array<int, bool> $excedeTopePorMedico */
        $excedeTopePorMedico = array_fill_keys($idsGrupo, false);
        /** @var array<int, array<string, int>> $horasSemanalesPorMedico */
        $horasSemanalesPorMedico = [];

        foreach ($guardiasSimuladas as $gs) {
            $uId = (int) $gs['user_id'];
            $hEf = (int) ($gs['horas_efectivas'] ?? $gs['horas']);
            if (isset($horasEfectivasPorMedico[$uId])) {
                $horasEfectivasPorMedico[$uId] += $hEf;
            }
            $fObj = Carbon::parse($gs['fecha']);
            $claveSemana = $fObj->format('o-\WW');
            if (!isset($horasSemanalesPorMedico[$uId][$claveSemana])) {
                $horasSemanalesPorMedico[$uId][$claveSemana] = (int) ($horasSemanaIsoMesAnterior[$uId][$claveSemana] ?? 0);
            }
            $horasSemanalesPorMedico[$uId][$claveSemana] += $hEf;
        }

        foreach ($medicos as $m) {
            $mId = (int) $m->id;
            $semanasM = $horasSemanalesPorMedico[$mId] ?? [];
            foreach ($semanasM as $semKey => $horasGuardiaSem) {
                if ($horasGuardiaSem > 48) {
                    $excedeTopePorMedico[$mId] = true;
                    $alertasTopeLegal[] = [
                        'user_id' => $mId,
                        'facultativo' => $m->name,
                        'semana' => $semKey,
                        'horas_semana' => $horasGuardiaSem,
                        'motivo' => "Supera el tope legal de 48h/semana de guardia en {$semKey} ({$horasGuardiaSem}h efectivas).",
                    ];
                }
            }
        }

        $equidadSimulada = $medicos->map(function (User $m) use ($stats, $horasEfectivasPorMedico, $excedeTopePorMedico): array {
            $mId = (int) $m->id;
            $s = $stats[$mId];
            $diarias = (int) $s['diarias_mes'];
            $findesPuros = (int) $s['findes_puros_mes'];
            $festivosMes = (int) $s['festivos_mes'] + (int) $s['especiales_mes'];
            $puntos = ($diarias * 1) + (($findesPuros + $festivosMes) * 2);

            return [
                'id' => $mId,
                'nombre' => str_replace(['Dr. ', 'Dra. '], '', $m->name),
                'porcentaje_jornada' => (int) ($s['porcentaje_jornada'] ?? 100),
                'dias_disponibles' => (int) $s['dias_disponibles_mes'],
                'diarias' => $diarias,
                'findes' => $findesPuros,
                'festivos' => $festivosMes,
                'festivos_anual' => (int) $s['festivos_anual'] + (int) $s['especiales_anual'],
                'especiales_anual' => (int) $s['especiales_anual'],
                'totales' => (int) $s['total_mes'],
                'horas_totales' => $horasEfectivasPorMedico[$mId] ?? (($diarias * 17) + (($findesPuros + $festivosMes) * 24)),
                'excede_tope_legal' => $excedeTopePorMedico[$mId] ?? false,
                'puntos' => $puntos,
            ];
        })->sortByDesc('puntos')->values()->all();

        if ($errorColapso !== null) {
            return [
                'ok' => false,
                'error' => $errorColapso,
                'resumen' => [
                    'mes' => $mes,
                    'anio' => $anio,
                    'colectivo' => $colectivo,
                    'turnos_nuevos' => count($guardiasAInsertar),
                    'manuales_respetados' => $guardiasManualesGrupo->count(),
                    'total_alertas_relajacion' => count($alertasRelajacion),
                    'total_alertas_tope_legal' => count($alertasTopeLegal),
                ],
                'alertas_relajacion' => $alertasRelajacion,
                'alertas_tope_legal' => $alertasTopeLegal,
                'guardias_simuladas' => $guardiasSimuladas,
                'equidad_simulada' => $equidadSimulada,
            ];
        }

        if ($persistir) {
            $turnosEliminados = 0;
            $idsTurnosReemplazados = [];

            DB::transaction(function () use ($especialidadId, $mes, $anio, $idsGrupo, $guardiasAInsertar, &$turnosEliminados, &$idsTurnosReemplazados): void {
                $idsTurnosReemplazados = Guardia::where('especialidad_id', $especialidadId)
                    ->whereIn('user_id', $idsGrupo)
                    ->whereMonth('fecha', $mes)
                    ->whereYear('fecha', $anio)
                    ->where('is_manual', false)
                    ->pluck('id')
                    ->map(fn($id): int => (int) $id)
                    ->all();

                $turnosEliminados = count($idsTurnosReemplazados);
                if ($turnosEliminados > 0) {
                    Guardia::whereIn('id', $idsTurnosReemplazados)->delete();
                }

                if (!empty($guardiasAInsertar)) {
                    Guardia::insert($guardiasAInsertar);
                }
            });

            // F3-02: Auditoría consolidada de lote en activity_log
            $etiquetaColectivo = $colectivo === self::COLECTIVO_RESIDENTES ? 'Residentes' : 'Adjuntos';
            $actor = Auth::user();
            $logger = activity('cuadrante')
                ->event('batch_generated')
                ->withProperties([
                    'especialidad_id' => $especialidadId,
                    'colectivo' => $colectivo,
                    'mes' => $mes,
                    'anio' => $anio,
                    'attributes' => [
                        'colectivo' => $etiquetaColectivo,
                        'periodo' => sprintf('%02d/%d', $mes, $anio),
                        'turnos_generados' => count($guardiasAInsertar),
                        'turnos_previos_reemplazados' => $turnosEliminados,
                        'manuales_respetados' => $guardiasManualesGrupo->count(),
                        'personas_por_dia' => $personasPorDia,
                        'agrupacion' => $agrupacion,
                        'distancia_minima_dias' => $distanciaMinimaDias,
                        'alertas_relajacion' => count($alertasRelajacion),
                        'alertas_tope_legal' => count($alertasTopeLegal),
                    ],
                ]);

            if ($actor instanceof User) {
                $logger->causedBy($actor);
            }

            $logger->log(
                "Generación masiva de cuadrante ({$etiquetaColectivo}) del periodo {$mes}/{$anio}: "
                . count($guardiasAInsertar) . " turnos asignados."
            );

            // F5-04: Notificar a los facultativos del colectivo sobre la publicación del cuadrante
            foreach ($medicos as $medicoNotif) {
                $mId = (int) $medicoNotif->id;
                $turnosM = (int) ($stats[$mId]['total_mes'] ?? 0);
                if ($turnosM > 0) {
                    $medicoNotif->notify(new CuadrantePublicadoNotification(
                        $especialidadId,
                        $colectivo,
                        $mes,
                        $anio,
                        $turnosM
                    ));
                }
            }

            SyncGoogleCalendarJob::dispatch($especialidadId, $idsGrupo, $mes, $anio, $idsTurnosReemplazados);
        }

        return [
            'ok' => true,
            'error' => null,
            'resumen' => [
                'mes' => $mes,
                'anio' => $anio,
                'colectivo' => $colectivo,
                'turnos_nuevos' => count($guardiasAInsertar),
                'manuales_respetados' => $guardiasManualesGrupo->count(),
                'total_alertas_relajacion' => count($alertasRelajacion),
                'total_alertas_tope_legal' => count($alertasTopeLegal),
            ],
            'alertas_relajacion' => $alertasRelajacion,
            'alertas_tope_legal' => $alertasTopeLegal,
            'guardias_simuladas' => $guardiasSimuladas,
            'equidad_simulada' => $equidadSimulada,
        ];
    }

    /**
     * Determina si una fecha está bloqueada por ausencia aprobada o limitación de guardia.
     *
     * @param Collection<int, Ausencia> $ausencias
     * @param Collection<int, LimitacionGuardia> $limitaciones
     */
    private function estaBloqueadoPorAusenciaOLimitacion(Carbon $fecha, Collection $ausencias, Collection $limitaciones): bool
    {
        $fechaStr = $fecha->format('Y-m-d');

        foreach ($ausencias as $a) {
            if ($a->tipo === 'rotacion_con_guardias') {
                continue;
            }
            $ini = Carbon::parse($a->fecha_inicio)->startOfDay();
            $fin = Carbon::parse($a->fecha_fin)->endOfDay();
            if ($fecha->between($ini, $fin)) {
                return true;
            }
        }

        foreach ($limitaciones as $l) {
            if ($l->tipo === 'dia_semana' && (int) $fecha->dayOfWeekIso === (int) $l->valor) {
                return true;
            }
            if ($l->tipo === 'fecha_concreta' && $fechaStr === $l->valor) {
                return true;
            }
            if ($l->tipo === 'periodo') {
                $parts = explode(',', (string) $l->valor);
                if (
                    count($parts) === 2 &&
                    $fecha->between(Carbon::parse($parts[0])->startOfDay(), Carbon::parse($parts[1])->endOfDay())
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determina si una fecha es la víspera inmediata del inicio de unas vacaciones aprobadas.
     *
     * @param Collection<int, Ausencia> $ausencias
     */
    private function esVisperaDeVacaciones(Carbon $fecha, Collection $ausencias): bool
    {
        $diaSiguiente = $fecha->copy()->addDay();
        foreach ($ausencias as $a) {
            if ($a->tipo === 'vacaciones') {
                $ini = Carbon::parse($a->fecha_inicio)->startOfDay();
                if ($diaSiguiente->isSameDay($ini)) {
                    return true;
                }
            }
        }

        return false;
    }
}
