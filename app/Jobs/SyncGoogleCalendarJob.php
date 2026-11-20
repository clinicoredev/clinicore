<?php

namespace App\Jobs;

use App\Models\Guardia;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SyncGoogleCalendarJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 30;

    /**
     * @param array<int, int> $userIds
     * @param array<int, int> $deletedGuardiaIds
     */
    public function __construct(
        public readonly int $especialidadId,
        public readonly array $userIds = [],
        public readonly ?int $mes = null,
        public readonly ?int $anio = null,
        public readonly array $deletedGuardiaIds = []
    ) {}

    public function uniqueId(): string
    {
        $uIds = $this->userIds;
        sort($uIds);
        $dIds = $this->deletedGuardiaIds;
        sort($dIds);

        return implode(':', [
            'gcal',
            (string) $this->especialidadId,
            (string) ($this->mes ?? 'all'),
            (string) ($this->anio ?? 'all'),
            md5(json_encode([$uIds, $dIds]) ?: ''),
        ]);
    }

    public function handle(GuardiaGeneratorService $generatorService): void
    {
        $query = User::where('especialidad_id', $this->especialidadId)
            ->where('gcal_sync_enabled', true);

        if (!empty($this->userIds)) {
            $query->whereIn('id', $this->userIds);
        }

        $usuarios = $query->get();
        if ($usuarios->isEmpty()) {
            return;
        }

        $mapaFestivos = $generatorService->obtenerMapaFestivos($this->especialidadId, $this->anio);

        foreach ($usuarios as $usuario) {
            $guardiasQuery = Guardia::withoutGlobalScopes()
                ->where('especialidad_id', $this->especialidadId)
                ->where('user_id', $usuario->id);

            if ($this->mes !== null) {
                $guardiasQuery->whereMonth('fecha', $this->mes);
            }
            if ($this->anio !== null) {
                $guardiasQuery->whereYear('fecha', $this->anio);
            }

            $turnos = $guardiasQuery->orderBy('fecha')->get()->map(function (Guardia $g) use ($generatorService, $mapaFestivos): array {
                $info = $generatorService->clasificarTipologiaGuardia($g, $mapaFestivos);
                return [
                    'guardia_id' => (int) $g->id,
                    'fecha' => $info['fecha_str'],
                    'etiqueta' => $info['etiqueta'],
                    'horas' => $info['horas'],
                    'es_24h' => $info['es_24h'],
                    'observaciones' => $g->observaciones,
                ];
            })->all();

            $calendarId = (string) ($usuario->gcal_calendar_id ?: 'primary');
            $accessToken = $this->resolverAccessToken($usuario);
            $modoSincronizacion = 'simulado_local';
            $eventosEnviadosApi = 0;
            $eventosEliminadosApi = 0;
            $erroresApi = 0;
            $tokenRevocado = false;

            if ($accessToken !== null && $accessToken !== '') {
                $modoSincronizacion = 'oauth2_api';
                $idsTurnosActivos = array_column($turnos, 'guardia_id');
                $idsAEliminar = array_values(array_diff(
                    array_unique(array_map('intval', $this->deletedGuardiaIds)),
                    $idsTurnosActivos
                ));

                // 1. Eliminar eventos borrados, vaciados o reasignados a otro facultativo
                foreach ($idsAEliminar as $deletedGuardiaId) {
                    if ($deletedGuardiaId <= 0) {
                        continue;
                    }
                    $eventId = 'clinicoreguardia' . $deletedGuardiaId;
                    $deleteUrl = sprintf(
                        'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
                        rawurlencode($calendarId),
                        rawurlencode($eventId)
                    );

                    try {
                        $delResp = Http::withToken($accessToken)->timeout(10)->delete($deleteUrl);
                        if (in_array($delResp->status(), [401, 403], true)) {
                            $this->desactivarPorTokenRevocado($usuario, 'Token rechazado por Google Calendar API (HTTP ' . $delResp->status() . ') al eliminar evento.');
                            $tokenRevocado = true;
                            break;
                        }
                        if ($delResp->successful() || in_array($delResp->status(), [404, 410], true)) {
                            $eventosEliminadosApi++;
                        } else {
                            $erroresApi++;
                        }
                    } catch (\Throwable $e) {
                        $erroresApi++;
                    }
                }

                // 2. Upsert (PUT con fallback a POST si devuelve 404) de turnos vigentes
                if (!$tokenRevocado) {
                    foreach ($turnos as $turno) {
                        $inicio = Carbon::parse($turno['fecha'], 'Europe/Madrid');
                        $fin = $inicio->copy();
                        if ($turno['es_24h']) {
                            $inicio->setTime(8, 0, 0);
                            $fin->addDay()->setTime(8, 0, 0);
                        } else {
                            $inicio->setTime(15, 0, 0);
                            $fin->addDay()->setTime(8, 0, 0);
                        }

                        $eventId = 'clinicoreguardia' . $turno['guardia_id'];
                        $putEndpoint = sprintf(
                            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
                            rawurlencode($calendarId),
                            rawurlencode($eventId)
                        );
                        $postEndpoint = sprintf(
                            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
                            rawurlencode($calendarId)
                        );

                        $payload = [
                            'id' => $eventId,
                            'summary' => '🏥 Guardia ' . $turno['etiqueta'],
                            'description' => sprintf(
                                'Turno de guardia %s [%dh]. %s',
                                $turno['etiqueta'],
                                $turno['horas'],
                                (string) ($turno['observaciones'] ?? '')
                            ),
                            'start' => [
                                'dateTime' => $inicio->toRfc3339String(),
                                'timeZone' => 'Europe/Madrid',
                            ],
                            'end' => [
                                'dateTime' => $fin->toRfc3339String(),
                                'timeZone' => 'Europe/Madrid',
                            ],
                        ];

                        try {
                            $resp = Http::withToken($accessToken)->timeout(10)->put($putEndpoint, $payload);

                            if ($resp->status() === 404) {
                                $resp = Http::withToken($accessToken)->timeout(10)->post($postEndpoint, $payload);
                            }

                            if (in_array($resp->status(), [401, 403], true)) {
                                $this->desactivarPorTokenRevocado($usuario, 'Token rechazado por Google Calendar API (HTTP ' . $resp->status() . ').');
                                $tokenRevocado = true;
                                break;
                            }

                            if ($resp->successful()) {
                                $eventosEnviadosApi++;
                            } else {
                                $erroresApi++;
                            }
                        } catch (\Throwable $e) {
                            $erroresApi++;
                        }
                    }
                }
            }

            if ($tokenRevocado) {
                continue;
            }

            $usuario->forceFill([
                'gcal_last_synced_at' => now(),
            ])->saveQuietly();

            activity('calendario')
                ->event($erroresApi > 0 ? 'gcal_sync_warning' : 'gcal_synced')
                ->causedBy($usuario)
                ->withProperties([
                    'especialidad_id' => $this->especialidadId,
                    'calendar_id' => $calendarId,
                    'modo' => $modoSincronizacion,
                    'turnos_sincronizados' => count($turnos),
                    'eventos_api_confirmados' => $eventosEnviadosApi,
                    'eventos_api_eliminados' => $eventosEliminadosApi,
                    'errores_api' => $erroresApi,
                    'mes' => $this->mes,
                    'anio' => $this->anio,
                ])
                ->log("Sincronización en cola de calendario externo completada ({$usuario->name}): " . count($turnos) . " turnos procesados.");
        }
    }

    private function resolverAccessToken(User $usuario): ?string
    {
        $accessToken = $usuario->gcal_access_token;
        if (empty($accessToken)) {
            return null;
        }

        $expirado = $usuario->gcal_token_expires_at !== null
            && Carbon::parse($usuario->gcal_token_expires_at)->isPast();

        if ($expirado && !empty($usuario->gcal_refresh_token)) {
            $clientId = trim((string) config('services.google.client_id', ''));
            $clientSecret = trim((string) config('services.google.client_secret', ''));

            if ($clientId === '' || $clientSecret === '') {
                return null;
            }

            try {
                $resp = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $usuario->gcal_refresh_token,
                    'grant_type' => 'refresh_token',
                ]);
            } catch (\Throwable $e) {
                activity('calendario')
                    ->event('gcal_sync_failed')
                    ->causedBy($usuario)
                    ->withProperties([
                        'especialidad_id' => $this->especialidadId,
                        'motivo' => 'Error de red al refrescar token OAuth2: ' . $e->getMessage(),
                    ])
                    ->log("Fallo de red al refrescar token OAuth2 de Google Calendar ({$usuario->name}).");

                return null;
            }

            if ($resp->successful()) {
                /** @var array<string, mixed> $data */
                $data = $resp->json() ?? [];
                $newToken = (string) ($data['access_token'] ?? '');
                $expiresIn = (int) ($data['expires_in'] ?? 3600);
                if ($newToken !== '') {
                    $usuario->forceFill([
                        'gcal_access_token' => $newToken,
                        'gcal_token_expires_at' => now()->addSeconds($expiresIn),
                    ])->saveQuietly();

                    return $newToken;
                }
            }

            if (in_array($resp->status(), [400, 401, 403], true)) {
                $this->desactivarPorTokenRevocado($usuario, 'Refresh token revocado o expirado en Google OAuth2 (HTTP ' . $resp->status() . ').');
                return null;
            }
        }

        return (string) $accessToken;
    }

    private function desactivarPorTokenRevocado(User $usuario, string $motivo): void
    {
        $usuario->forceFill([
            'gcal_sync_enabled' => false,
            'gcal_access_token' => null,
            'gcal_refresh_token' => null,
            'gcal_token_expires_at' => null,
        ])->saveQuietly();

        activity('calendario')
            ->event('gcal_sync_failed')
            ->causedBy($usuario)
            ->withProperties([
                'especialidad_id' => $this->especialidadId,
                'motivo' => $motivo,
            ])
            ->log("Sincronización OAuth2 desactivada automáticamente para {$usuario->name}: {$motivo}");
    }
}
