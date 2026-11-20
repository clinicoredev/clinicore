<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Guardia;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CalendarFeedController extends Controller
{
    public function __construct(
        private readonly GuardiaGeneratorService $generatorService
    ) {}

    public function feed(string $token): Response
    {
        $user = User::with('especialidad.hospital')->where('calendar_token', $token)->firstOrFail();

        $fechaDesde = now()->subMonths(2)->startOfDay()->format('Y-m-d');

        $guardias = Guardia::where('user_id', $user->id)
            ->where('fecha', '>=', $fechaDesde)
            ->orderBy('fecha', 'asc')
            ->get();

        $mapaFestivos = $user->especialidad_id
            ? $this->generatorService->obtenerMapaFestivos((int) $user->especialidad_id, null, $fechaDesde)
            : [];

        $nombreLimpio = str_replace(['Dr. ', 'Dra. '], '', $user->name);
        $ubicacion = ($user->especialidad->nombre ?? 'Servicio') . ' - ' . ($user->especialidad->hospital->nombre ?? 'Hospital');

        $brandName = (string) config('clinicore.brand.name', 'CliniCore');
        $brandDomain = (string) config('clinicore.brand.domain', 'clinicore.es');
        $includeWatermark = (bool) config('clinicore.exports.watermark_enabled', true);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//{$brandName}//Planificacion de Guardias//ES\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Guardias " . $nombreLimpio . "\r\n";
        $ical .= "X-WR-TIMEZONE:Europe/Madrid\r\n";
        $ical .= "REFRESH-INTERVAL;VALUE=DURATION:PT4H\r\n";

        foreach ($guardias as $guardia) {
            $info = $this->generatorService->clasificarTipologiaGuardia($guardia, $mapaFestivos);
            $fechaStr = $info['fecha_str'];

            // Fijamos la hora en zona horaria de España y luego convertimos a UTC para el estándar iCal
            $inicio = Carbon::parse($fechaStr, 'Europe/Madrid');
            $fin = $inicio->copy();

            if ($info['es_24h']) {
                $inicio->setTime(8, 0, 0);
                $fin->addDay()->setTime(8, 0, 0);
            } else {
                $inicio->setTime(15, 0, 0);
                $fin->addDay()->setTime(8, 0, 0);
            }

            $modalidad = $info['etiqueta'];
            if ($info['festivo_descripcion'] !== null) {
                $modalidad .= ' (' . $info['festivo_descripcion'] . ')';
            }

            $resumen = '🏥 Guardia ' . $info['etiqueta'];

            $descripcionEvento = 'Turno de guardia ' . $modalidad . ' [' . $info['horas'] . 'h].';
            if (!empty($guardia->observaciones)) {
                $descripcionEvento .= ' ' . $guardia->observaciones;
            }
            if ($includeWatermark) {
                $descripcionEvento .= " · Generado con {$brandName} ({$brandDomain})";
            }

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:guardia_{$guardia->id}@{$brandDomain}\r\n";
            $ical .= "DTSTAMP:" . now()->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "DTSTART:" . $inicio->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "DTEND:" . $fin->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "SUMMARY:" . $resumen . "\r\n";
            $ical .= "LOCATION:" . $ubicacion . "\r\n";
            $ical .= "DESCRIPTION:" . $descripcionEvento . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR\r\n";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="guardias.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function configurarSync(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $validated = $request->validate([
            'gcal_sync_enabled' => ['required', 'boolean'],
            'gcal_calendar_id' => ['nullable', 'string', 'max:190'],
            'sincronizar_ahora' => ['nullable', 'boolean'],
        ]);

        $usuario->forceFill([
            'gcal_sync_enabled' => (bool) $validated['gcal_sync_enabled'],
            'gcal_calendar_id' => $validated['gcal_calendar_id'] ?: 'primary',
        ])->save();

        if ($usuario->gcal_sync_enabled && $request->boolean('sincronizar_ahora', true) && $usuario->especialidad_id !== null) {
            SyncGoogleCalendarJob::dispatch((int) $usuario->especialidad_id, [(int) $usuario->id]);
        }

        return back()->with('success', 'Preferencias de sincronización con Google Calendar actualizadas.');
    }

    /**
     * F5-05 / F6-01: Inicia el flujo de autorización OAuth2 con Google Calendar API.
     */
    public function oauthRedirect(Request $request): RedirectResponse
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $clientSecret = trim((string) config('services.google.client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            return redirect()->route('dashboard')->withErrors([
                'gcal' => 'La integración OAuth2 con Google Calendar no está configurada en el servidor (faltan GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).',
            ]);
        }

        $redirectUri = url((string) config('services.google.redirect', '/calendario/oauth/callback'));
        $state = Str::random(32);

        $request->session()->put('gcal_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    /**
     * F5-05 / F6-01: Procesa el callback OAuth2 de Google Calendar API y almacena los tokens cifrados.
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $expectedState = (string) $request->session()->pull('gcal_oauth_state', '');
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
            abort(403, 'Estado OAuth2 inválido.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('dashboard')->withErrors(['gcal' => 'No se recibió código de autorización de Google.']);
        }

        $clientId = trim((string) config('services.google.client_id', ''));
        $clientSecret = trim((string) config('services.google.client_secret', ''));
        if ($clientId === '' || $clientSecret === '') {
            return redirect()->route('dashboard')->withErrors([
                'gcal' => 'Credenciales OAuth2 de Google Calendar no configuradas en el servidor.',
            ]);
        }

        $redirectUri = url((string) config('services.google.redirect', '/calendario/oauth/callback'));

        try {
            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')->withErrors([
                'gcal' => 'No se pudo conectar con Google OAuth2: ' . $e->getMessage(),
            ]);
        }

        if (!$response->successful()) {
            return redirect()->route('dashboard')->withErrors(['gcal' => 'Error al intercambiar el código OAuth2 con Google Calendar.']);
        }

        /** @var array<string, mixed> $tokenData */
        $tokenData = $response->json() ?? [];
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        $refreshToken = isset($tokenData['refresh_token']) ? (string) $tokenData['refresh_token'] : $usuario->gcal_refresh_token;
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        if ($accessToken === '') {
            return redirect()->route('dashboard')->withErrors(['gcal' => 'Respuesta de token OAuth2 inválida.']);
        }

        $usuario->forceFill([
            'gcal_sync_enabled' => true,
            'gcal_calendar_id' => $usuario->gcal_calendar_id ?: 'primary',
            'gcal_access_token' => $accessToken,
            'gcal_refresh_token' => $refreshToken,
            'gcal_token_expires_at' => now()->addSeconds($expiresIn),
        ])->save();

        if ($usuario->especialidad_id !== null) {
            SyncGoogleCalendarJob::dispatch((int) $usuario->especialidad_id, [(int) $usuario->id]);
        }

        return redirect()->route('dashboard')->with('success', 'Cuenta de Google Calendar vinculada correctamente mediante OAuth2.');
    }

    /**
     * F5-05: Revoca y limpia las credenciales OAuth2 de Google Calendar del usuario.
     */
    public function oauthDisconnect(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $usuario->forceFill([
            'gcal_sync_enabled' => false,
            'gcal_access_token' => null,
            'gcal_refresh_token' => null,
            'gcal_token_expires_at' => null,
        ])->save();

        return back()->with('success', 'Vinculación OAuth2 con Google Calendar desconectada.');
    }
}
