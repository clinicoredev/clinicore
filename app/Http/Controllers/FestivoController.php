<?php

namespace App\Http\Controllers;

use App\Models\Festivo;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;

class FestivoController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        
        $festivos = Festivo::where('especialidad_id', $usuario->especialidad_id)
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function($f) {
                $rawDesc = $f->descripcion ?: 'Festivo Oficial';
                $esEspecial = str_starts_with(strtoupper($rawDesc), '[ESPECIAL]');
                $limpiaDesc = trim(str_ireplace('[ESPECIAL]', '', $rawDesc)) ?: 'Festivo Especial';

                return [
                    'id' => $f->id,
                    'fecha_raw' => Carbon::parse($f->fecha)->format('Y-m-d'),
                    'fecha_formateada' => Carbon::parse($f->fecha)->format('d/m/Y'),
                    'dia_semana' => ucfirst(Carbon::parse($f->fecha)->locale('es')->dayName),
                    'mes' => ucfirst(Carbon::parse($f->fecha)->locale('es')->monthName),
                    'anio' => Carbon::parse($f->fecha)->year,
                    'descripcion' => $limpiaDesc,
                    'es_especial' => $esEspecial
                ];
            });

        $festivosAgrupados = $festivos->groupBy('anio');

        return Inertia::render('Festivos/Index', [
            'festivosAgrupados' => $festivosAgrupados,
            'anioActual' => now()->year,
            'permisos' => [
                'es_admin' => $usuario->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes'])
            ]
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes'])) {
            abort(403, 'No tienes permisos para gestionar festivos.');
        }

        $request->validate([
            'fecha' => 'required|date',
            'descripcion' => 'nullable|string|max:85',
            'es_especial' => 'nullable|boolean'
        ]);

        $especialidadId = $request->user()->especialidad_id;

        $existe = Festivo::where('especialidad_id', $especialidadId)
                         ->whereDate('fecha', $request->fecha)
                         ->exists();

        if ($existe) {
            return back()->withErrors(['festivos' => 'Ese día ya está marcado como festivo en tu servicio.']);
        }

        $nombreBase = trim(str_ireplace('[ESPECIAL]', '', $request->descripcion ?: 'Festivo Oficial'));
        $descripcionFinal = $request->boolean('es_especial') 
            ? '[ESPECIAL] ' . $nombreBase 
            : $nombreBase;

        Festivo::create([
            'especialidad_id' => $especialidadId,
            'fecha' => $request->fecha,
            'descripcion' => $descripcionFinal
        ]);

        return back()->with('success', 'Festivo registrado correctamente.');
    }

    public function precargarNacionales(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes'])) {
            abort(403, 'No tienes permisos para gestionar festivos.');
        }

        $anio = (int) $request->input('anio', now()->year);
        $especialidadId = $request->user()->especialidad_id;

        // Marcamos automáticamente con [ESPECIAL] los días clave de Navidad/Año Nuevo/Reyes
        $nacionales = [
            "$anio-01-01" => '[ESPECIAL] Año Nuevo',
            "$anio-01-06" => '[ESPECIAL] Epifanía del Señor (Reyes)',
            "$anio-05-01" => 'Fiesta del Trabajo',
            "$anio-08-15" => 'Asunción de la Virgen',
            "$anio-10-12" => 'Fiesta Nacional de España',
            "$anio-11-01" => 'Todos los Santos',
            "$anio-12-06" => 'Día de la Constitución',
            "$anio-12-08" => 'Inmaculada Concepción',
            "$anio-12-24" => '[ESPECIAL] Nochebuena',
            "$anio-12-25" => '[ESPECIAL] Natividad del Señor (Navidad)',
            "$anio-12-31" => '[ESPECIAL] Nochevieja',
        ];

        $insertados = 0;
        foreach ($nacionales as $fecha => $nombre) {
            $existe = Festivo::where('especialidad_id', $especialidadId)
                             ->whereDate('fecha', $fecha)
                             ->exists();
            if (!$existe) {
                Festivo::create([
                    'especialidad_id' => $especialidadId,
                    'fecha' => $fecha,
                    'descripcion' => $nombre
                ]);
                $insertados++;
            }
        }

        return back()->with('success', "Se han importado $insertados festivos (incluyendo especiales) para el año $anio.");
    }

    public function destroy(Request $request, Festivo $festivo)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes']) || $festivo->especialidad_id !== $request->user()->especialidad_id) {
            abort(403, 'Acceso denegado.');
        }

        $festivo->delete();

        return back()->with('success', 'Festivo eliminado del calendario.');
    }
}