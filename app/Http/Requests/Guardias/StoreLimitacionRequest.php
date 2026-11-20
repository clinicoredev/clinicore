<?php

namespace App\Http\Requests\Guardias;

use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Http\FormRequest;

class StoreLimitacionRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'tipo' => 'required|in:dia_semana,fecha_concreta,periodo',
            'valor' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'motivo' => 'nullable|string|max:100',
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();
        if ($user) {
            app(GuardiaGeneratorService::class)->validarFacultativoEnColectivo(
                (int) $this->input('user_id'),
                (int) $user->especialidad_id,
                $this->colectivo()
            );
        }
    }

    public function valorConsolidado(): ?string
    {
        if ($this->input('tipo') === 'periodo') {
            return ((string) $this->input('fecha_inicio', '')) . ',' . ((string) $this->input('fecha_fin', ''));
        }

        $val = $this->input('valor');
        return $val !== null ? (string) $val : null;
    }
}
