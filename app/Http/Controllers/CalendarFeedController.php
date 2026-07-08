<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Guardia;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarFeedController extends Controller
{
    public function feed($token)
    {
        // Buscamos al médico dueño de este enlace secreto
        $user = User::where('calendar_token', $token)->firstOrFail();
        
        $guardias = Guardia::where('user_id', $user->id)
            ->where('fecha', '>=', now()->subMonths(1)) // Traemos algo de histórico
            ->get();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Clinicore SaaS//Turnos Medicos//ES\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "X-WR-CALNAME:Guardias " . str_replace(['Dr. ', 'Dra. '], '', $user->name) . "\r\n";
        $ical .= "REFRESH-INTERVAL;VALUE=DURATION:PT4H\r\n"; // Sugerimos al móvil que se actualice cada 4 horas

        foreach ($guardias as $guardia) {
            $inicio = Carbon::parse($guardia->fecha);
            $fin = $inicio->copy();
            
            // Lógica de horarios (puedes ajustar estas horas según tu hospital)
            if ($guardia->tipo === 'festivo_24h') {
                $inicio->setHour(8)->setMinute(0);
                $fin->addDay()->setHour(8)->setMinute(0);
            } else {
                $inicio->setHour(15)->setMinute(0);
                $fin->addDay()->setHour(8)->setMinute(0); // Sale al día siguiente a las 8am
            }

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:guardia_{$guardia->id}@clinicore.app\r\n";
            $ical .= "DTSTAMP:" . now()->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "DTSTART:" . $inicio->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "DTEND:" . $fin->setTimezone('UTC')->format('Ymd\THis\Z') . "\r\n";
            $ical .= "SUMMARY:🏥 Guardia " . ($guardia->tipo === 'festivo_24h' ? '24h' : '17h') . "\r\n";
            $ical .= "DESCRIPTION:" . ($guardia->observaciones ?? 'Turno ordinario') . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }
        $ical .= "END:VCALENDAR\r\n";

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="guardias.ics"',
        ]);
    }
}