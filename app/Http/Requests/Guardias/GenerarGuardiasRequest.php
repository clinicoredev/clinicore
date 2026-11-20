<?php

namespace App\Http\Requests\Guardias;

use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Http\FormRequest;

class GenerarGuardiasRequest extends FormRequest
{
    public function colectivo(): string
    {
        return $this->is('residentes/*')
            ? GuardiaGeneratorService::COLECTIVO_RESIDENTES
            : GuardiaGeneratorService::COLECTIVO_ADJUNTOS;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($this->colectivo() === GuardiaGeneratorService::COLECTIVO_RESIDENTES) {
            return $user->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes', 'Residente Mayor']);
        }

        return $user->hasRole('Jefe de Servicio');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mes' => 'required|integer|between:1,12',
            'anio' => 'required|integer',
            'personas_por_dia' => 'required|integer|min:1|max:10',
            'usar_plantilla_completa' => 'boolean',
            'medicos_incluidos' => 'nullable|array',
            'respetar_salientes' => 'boolean',
            'distancia_minima_dias' => 'nullable|integer|min:0|max:10',
            'proteger_vispera_vacaciones' => 'boolean',
            'max_guardias_mes' => 'nullable|integer|min:0',
            'max_findes_mes' => 'nullable|integer|min:0',
            'max_diarias_mes' => 'nullable|integer|min:0',
            'prorratear_ausencias' => 'boolean',
            'usar_memoria_anual' => 'boolean',
            'agrupacion' => 'required|string|in:ninguna,s_d,v_s,v_s_d',
            'modalidad' => 'nullable|string|in:presencial,localizada',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generatorParams(): array
    {
        return [
            'mes' => (int) $this->input('mes'),
            'anio' => (int) $this->input('anio'),
            'personas_por_dia' => (int) $this->input('personas_por_dia'),
            'usar_plantilla_completa' => $this->boolean('usar_plantilla_completa', true),
            'medicos_incluidos' => $this->input('medicos_incluidos', []),
            'respetar_salientes' => $this->boolean('respetar_salientes', true),
            'distancia_minima_dias' => (int) $this->input('distancia_minima_dias', 1),
            'proteger_vispera_vacaciones' => $this->boolean('proteger_vispera_vacaciones', true),
            'max_guardias_mes' => (int) $this->input('max_guardias_mes', 0),
            'max_findes_mes' => (int) $this->input('max_findes_mes', 0),
            'max_diarias_mes' => (int) $this->input('max_diarias_mes', 0),
            'prorratear_ausencias' => $this->boolean('prorratear_ausencias', true),
            'usar_memoria_anual' => $this->boolean('usar_memoria_anual', true),
            'agrupacion' => (string) $this->input('agrupacion', 'ninguna'),
            'modalidad' => (string) $this->input('modalidad', 'presencial'),
        ];
    }
}
