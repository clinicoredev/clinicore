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
            ->map(fn($f) => [
                'id' => $f->id,
                'fecha_raw' => $f->fecha,
                'fecha_formateada' => Carbon::parse($f->fecha)->format('d/m/Y'),
                'mes' => Carbon::parse($f->fecha)->locale('es')->monthName,
                'anio' => Carbon::parse($f->fecha)->year,
                'descripcion' => $f->descripcion
            ]);

        // Agrupamos los festivos por Año para mostrarlos más limpios en la vista
        $festivosAgrupados = $festivos->groupBy('anio');

        return Inertia::render('Festivos/Index', [
            'festivosAgrupados' => $festivosAgrupados,
            'permisos' => [
                'es_admin' => $usuario->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])
            ]
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes'])) {
            abort(403, 'No tienes permisos para gestionar festivos.');
        }

        $request->validate([
            'fecha' => 'required|date',
            'descripcion' => 'nullable|string|max:100'
        ]);

        $especialidadId = $request->user()->especialidad_id;

        $existe = Festivo::where('especialidad_id', $especialidadId)
                         ->where('fecha', $request->fecha)
                         ->exists();

        if ($existe) {
            return back()->withErrors(['festivos' => 'Ese día ya está marcado como festivo.']);
        }

        Festivo::create([
            'especialidad_id' => $especialidadId,
            'fecha' => $request->fecha,
            'descripcion' => $request->descripcion
        ]);

        return back()->with('success', 'Festivo registrado correctamente.');
    }

    public function destroy(Request $request, Festivo $festivo)
    {
        if (!$request->user()->hasAnyRole(['Jefe de Servicio', 'Admin de Residentes']) || $festivo->especialidad_id !== $request->user()->especialidad_id) {
            abort(403, 'Acceso denegado.');
        }

        $festivo->delete();

        return back()->with('success', 'Festivo eliminado del calendario.');
    }
}