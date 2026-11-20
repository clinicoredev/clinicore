<?php

namespace App\Http\Controllers;

use App\Exports\GuardiasExport;
use App\Http\Requests\Guardias\GenerarGuardiasRequest;
use App\Http\Requests\Guardias\GuardarGuardiaManualRequest;
use App\Http\Requests\Guardias\PermutarGuardiaRequest;
use App\Http\Requests\Guardias\StoreLimitacionRequest;
use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Guardia;
use App\Models\LimitacionGuardia;
use App\Services\GuardiaGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ResidenteGuardiaController extends Controller
{
    private const ROLES_GESTION_RESIDENTES = ['Jefe de Servicio', 'Tutor de Residentes', 'Residente Mayor'];

    public function __construct(
        private readonly GuardiaGeneratorService $generatorService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $usuario = $request->user();
        $mes = (int) $request->query('mes', now()->month);
        $anio = (int) $request->query('anio', now()->year);

        $datos = $this->generatorService->obtenerDatosIndex(
            (int) $usuario->especialidad_id,
            $mes,
            $anio,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES
        );

        return inertia('Residentes/Guardias/Index', array_merge($datos, [
            'permisos' => ['es_jefe' => $usuario->hasAnyRole(self::ROLES_GESTION_RESIDENTES)],
        ]));
    }

    public function simularAlgoritmo(GenerarGuardiasRequest $request): JsonResponse
    {
        $resultado = $this->generatorService->simularCuadrante(
            (int) $request->user()->especialidad_id,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES,
            $request->generatorParams()
        );

        return response()->json($resultado);
    }

    public function generarAlgoritmo(GenerarGuardiasRequest $request): RedirectResponse
    {
        $error = $this->generatorService->generarCuadrante(
            (int) $request->user()->especialidad_id,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES,
            $request->generatorParams()
        );

        if ($error !== null) {
            return back()->withErrors(['algoritmo' => $error]);
        }

        return back()->with('success', 'Cuadrante generado con Agrupación Inteligente por Cuota y Equidad Anual de Festivos.');
    }

    public function verificarConflicto(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $payload = $request->validate([
            'modo' => 'nullable|string|in:asignacion,permuta',
            'user_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'guardia_excluida_id' => 'nullable|integer',
            'origen_id' => 'nullable|integer',
            'destino_id' => 'nullable|integer',
            'distancia_minima_dias' => 'nullable|integer|min:0|max:10',
        ]);

        $modo = (string) ($payload['modo'] ?? (isset($payload['origen_id'], $payload['destino_id']) ? 'permuta' : 'asignacion'));

        if ($modo === 'permuta') {
            if (!$usuario->hasAnyRole(self::ROLES_GESTION_RESIDENTES)) {
                $guardiaOrigen = Guardia::withoutGlobalScopes()->findOrFail((int) ($payload['origen_id'] ?? 0));
                if (
                    (int) $guardiaOrigen->especialidad_id !== (int) $usuario->especialidad_id
                    || (int) $guardiaOrigen->user_id !== (int) $usuario->id
                ) {
                    abort(403);
                }
            }
        } elseif (!$usuario->hasAnyRole(self::ROLES_GESTION_RESIDENTES)) {
            abort(403);
        }

        $resultado = $this->generatorService->evaluarConflictosEnTiempoReal(
            (int) $usuario->especialidad_id,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES,
            $payload
        );

        return response()->json($resultado);
    }

    public function storeLimitacion(StoreLimitacionRequest $request): RedirectResponse
    {
        LimitacionGuardia::create([
            'especialidad_id' => $request->user()->especialidad_id,
            'user_id' => (int) $request->input('user_id'),
            'tipo' => (string) $request->input('tipo'),
            'valor' => $request->valorConsolidado(),
            'motivo' => $request->input('motivo'),
        ]);

        return back()->with('success', 'Regla registrada para el residente.');
    }

    public function destroyLimitacion(Request $request, LimitacionGuardia $limitacion): RedirectResponse
    {
        if (!$request->user()->hasAnyRole(self::ROLES_GESTION_RESIDENTES) || $limitacion->especialidad_id !== $request->user()->especialidad_id) {
            abort(403);
        }
        $this->generatorService->validarFacultativoEnColectivo(
            (int) $limitacion->user_id,
            (int) $request->user()->especialidad_id,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES
        );
        $limitacion->delete();
        return back();
    }

    public function guardarGuardiaManual(GuardarGuardiaManualRequest $request): RedirectResponse
    {
        $userId = (int) $request->input('user_id');
        $fecha = (string) $request->input('fecha');

        if (!$request->boolean('forzar', false)) {
            if ($conflicto = $this->generatorService->checkConflict($userId, $fecha, false, GuardiaGeneratorService::COLECTIVO_RESIDENTES)) {
                return back()->withErrors(['conflicto' => 'Operación denegada: ' . $conflicto]);
            }
            if ($colision = $this->generatorService->checkPermutaColisionOSaliente($userId, $fecha, 0)) {
                return back()->withErrors(['conflicto' => 'Operación denegada: ' . $colision]);
            }
        }

        $jefe = $request->user();
        $especialidadId = (int) $jefe->especialidad_id;

        $idsPrevios = Guardia::where('especialidad_id', $especialidadId)
            ->whereDate('fecha', $fecha)
            ->where('user_id', $userId)
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        if (!empty($idsPrevios)) {
            Guardia::whereIn('id', $idsPrevios)->delete();
        }

        Guardia::create([
            'especialidad_id' => $especialidadId,
            'user_id' => $userId,
            'fecha' => $fecha,
            'tipo' => (string) $request->input('tipo'),
            'modalidad' => (string) $request->input('modalidad', 'presencial'),
            'estado' => 'programada',
            'is_manual' => true,
            'observaciones' => 'Fijado manualmente',
        ]);

        $fechaCarbon = Carbon::parse($fecha);
        SyncGoogleCalendarJob::dispatch($especialidadId, [$userId], $fechaCarbon->month, $fechaCarbon->year, $idsPrevios);

        return back()->with('success', 'Guardia manual consolidada.');
    }

    public function permutar(PermutarGuardiaRequest $request): RedirectResponse
    {
        $origenId = (int) $request->input('origen_id');
        $destinoId = (int) $request->input('destino_id');
        $especialidadId = (int) $request->user()->especialidad_id;

        $error = $this->generatorService->ejecutarPermuta(
            $origenId,
            $destinoId,
            $especialidadId,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES
        );

        if ($error !== null) {
            return back()->withErrors(['conflicto' => $error]);
        }

        $idsAfectados = Guardia::whereIn('id', [$origenId, $destinoId])
            ->pluck('user_id')
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        SyncGoogleCalendarJob::dispatch($especialidadId, $idsAfectados, null, null, [$origenId, $destinoId]);

        return back()->with('success', 'Permuta realizada: Turnos intercambiados con éxito.');
    }

    public function destroy(Request $request, Guardia $guardia): RedirectResponse
    {
        if (!$request->user()->hasAnyRole(self::ROLES_GESTION_RESIDENTES)) {
            abort(403);
        }
        if ($guardia->especialidad_id !== $request->user()->especialidad_id) {
            abort(403, 'Acceso denegado.');
        }

        $especialidadId = (int) $request->user()->especialidad_id;
        $userId = (int) $guardia->user_id;
        $guardiaId = (int) $guardia->id;
        $fechaCarbon = Carbon::parse($guardia->fecha);

        $this->generatorService->validarFacultativoEnColectivo(
            $userId,
            $especialidadId,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES
        );

        $guardia->delete();
        SyncGoogleCalendarJob::dispatch($especialidadId, [$userId], $fechaCarbon->month, $fechaCarbon->year, [$guardiaId]);

        return back()->with('success', 'Turno liberado correctamente.');
    }

    public function vaciarMes(Request $request): RedirectResponse
    {
        $request->validate(['mes' => 'required|integer', 'anio' => 'required|integer']);

        if (!$request->user()->hasAnyRole(self::ROLES_GESTION_RESIDENTES)) {
            abort(403);
        }

        $especialidadId = (int) $request->user()->especialidad_id;
        $mes = (int) $request->mes;
        $anio = (int) $request->anio;

        $idsColectivo = $this->generatorService
            ->queryMedicosColectivo($especialidadId, GuardiaGeneratorService::COLECTIVO_RESIDENTES)
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        $idsEliminados = Guardia::where('especialidad_id', $especialidadId)
            ->whereIn('user_id', $idsColectivo)
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        $this->generatorService->vaciarMesColectivo(
            $especialidadId,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES,
            $mes,
            $anio,
            $request->user()
        );

        SyncGoogleCalendarJob::dispatch($especialidadId, $idsColectivo, $mes, $anio, $idsEliminados);

        return back()->with('success', 'Calendario del mes reseteado por completo.');
    }

    public function exportarExcel(Request $request): BinaryFileResponse
    {
        $mes = (int) $request->query('mes', now()->month);
        $anio = (int) $request->query('anio', now()->year);
        $especialidadId = (int) $request->user()->especialidad_id;

        /** @var array<int, int> $idsResidentes */
        $idsResidentes = $this->generatorService
            ->queryMedicosColectivo($especialidadId, GuardiaGeneratorService::COLECTIVO_RESIDENTES)
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->toArray();

        return Excel::download(new GuardiasExport($especialidadId, $mes, $anio, $idsResidentes), "Cuadrante_Residentes_{$mes}_{$anio}.xlsx");
    }

    public function exportarPdf(Request $request): Response
    {
        $mes = (int) $request->query('mes', now()->month);
        $anio = (int) $request->query('anio', now()->year);
        $usuario = $request->user();

        $datosPdf = $this->generatorService->construirDatosReportePdf(
            (int) $usuario->especialidad_id,
            $mes,
            $anio,
            GuardiaGeneratorService::COLECTIVO_RESIDENTES
        );

        $pdf = Pdf::loadView('reportes.guardias_pdf', array_merge($datosPdf, [
            'hospital' => $usuario->especialidad->hospital->nombre ?? 'Hospital',
            'especialidad' => ($usuario->especialidad->nombre ?? 'Unidad') . ' (Residentes)',
            'includeWatermark' => (bool) config('clinicore.exports.watermark_enabled', true),
            'includeDiagonalWatermark' => (bool) config('clinicore.exports.pdf_diagonal_watermark', true),
            'brandName' => (string) config('clinicore.brand.name', 'CliniCore'),
            'brandDomain' => (string) config('clinicore.brand.domain', 'clinicore.es'),
        ]));

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download("Cuadrante_Residentes_{$mes}_{$anio}.pdf");
    }
}
