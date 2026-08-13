<?php

namespace App\Http\Controllers;

use App\Models\Ausencia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AusenciaController extends Controller
{
    // 1. PANTALLA PRINCIPAL: PINTAR LA TABLA SEGÚN EL RANGO MILITAR
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Preguntamos a Spatie: ¿Este señor manda aquí?
        $esJefe = $usuario->hasRole('Jefe de Servicio') || $usuario->hasRole('SuperAdmin');

        // Preparamos la consulta optimizada (Traemos solo el id y el name de los usuarios vinculados)
        $query = Ausencia::where('especialidad_id', $usuario->especialidad_id)
            ->with(['solicitante:id,name', 'revisor:id,name']);

        // SI NO ES JEFE, LE PONEMOS LAS ANTHEJERAS DE CABALLO: SOLO VE LO SUYO
        if (!$esJefe) {
            $query->where('user_id', $usuario->id);
        }

        $ausencias = $query->latest()->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'tipo' => ucfirst(str_replace('_', ' ', $a->tipo)), // 'asuntos_propios' -> 'Asuntos propios'
                'solicitante' => $a->solicitante->name,
                'revisor' => $a->revisor?->name ?? 'Pendiente de firma',
                'fechas' => $a->fecha_inicio->format('d/m/Y') . ' al ' . $a->fecha_fin->format('d/m/Y'),
                'dias_totales' => $a->fecha_inicio->diffInDays($a->fecha_fin) + 1, // Matemáticas Carbon
                'motivo' => $a->motivo ?? 'Sin especificar',
                'estado' => $a->estado,
            ];
        });

        // NUEVO: Obtener la lista de facultativos del Tenant (excluyendo SuperAdmins)
        $medicos = User::where('especialidad_id', $usuario->especialidad_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'SuperAdmin'))
            ->get(['id', 'name']);

        return Inertia::render('Ausencias/Index', [
            'ausencias' => $ausencias,
            'medicos' => $medicos, // Se pasa la lista de médicos a la vista
            'permisos' => [
                'es_jefe' => $esJefe
            ]
        ]);
    }

    // 2. RECIBIR EL FORMULARIO DE PEDIR DÍAS:
    public function store(Request $request)
    {
        $usuario = $request->user();
        $esJefe = $usuario->hasRole('Jefe de Servicio');

        // Validamos los datos, añadiendo user_id de forma condicional
        $validated = $request->validate([
            'user_id' => [
                $esJefe ? 'required' : 'nullable', 
                'exists:users,id'
            ],
            'tipo' => ['required', Rule::in(['congreso', 'vacaciones', 'asuntos_propios', 'baja_medica'])],
            // Permitimos fechas pasadas para el Jefe (ej: baja médica que empezó ayer)
            'fecha_inicio' => ['required', 'date', $esJefe ? '' : 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        // Determinar a quién pertenece la ausencia y el estado inicial
        // Si el usuario no es Jefe, el user_id siempre es el suyo y el estado es 'pendiente'.
        // Si es Jefe, asigna la ausencia al usuario seleccionado y la pre-aprueba.
        $targetUserId = $esJefe ? $validated['user_id'] : $usuario->id;
        
        $estadoFinal = 'pendiente';
        $aprobadoPor = null;

        if ($esJefe) {
            $estadoFinal = 'aprobada';
            $aprobadoPor = $usuario->id; // El Jefe se auto-firma la autorización
        }

        Ausencia::create([
            'especialidad_id' => $usuario->especialidad_id,
            'user_id' => $targetUserId,
            'tipo' => $validated['tipo'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'motivo' => $validated['motivo'],
            'estado' => $estadoFinal,
            'aprobado_por' => $aprobadoPor
        ]);

        return back();
    }

    // 3. EL JEFE PULSA "APROBAR" O "DENEGAR":
    public function resolver(Request $request, Ausencia $ausencia)
    {
        // Escudo anti-hackers de segunda línea:
        if (!$request->user()->hasRole('Jefe de Servicio')) {
            abort(403, 'Alarma de seguridad: Un facultativo sin rango ha intentado firmar un documento oficial.');
        }

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['aprobada', 'denegada'])],
        ]);

        $ausencia->update([
            'estado' => $validated['estado'],
            'aprobado_por' => $request->user()->id,
        ]);

        $ausencia->solicitante->notify(new \App\Notifications\EstadoAusenciaModificado($ausencia));

        return back();
    }
}